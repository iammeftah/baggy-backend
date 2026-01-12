<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\ChatbotConversation;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    /**
     * Get user's chat history
     */
    public function history(Request $request): JsonResponse
    {
        $conversations = ChatbotConversation::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($conv) {
                return [
                    'id' => $conv->id,
                    'type' => $conv->sender,
                    'text' => $conv->message,
                    'products' => $conv->recommended_products,
                    'timestamp' => $conv->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Clear user's chat history
     */
    public function clearHistory(Request $request): JsonResponse
    {
        ChatbotConversation::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chat history cleared',
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $userMessage = $request->message;
        $userId = $request->user()->id;

        try {
            // Save user message
            ChatbotConversation::create([
                'user_id' => $userId,
                'message' => $userMessage,
                'sender' => 'user',
            ]);

            // Get conversation history for context (last 10 messages)
            $conversationHistory = ChatbotConversation::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse()
                ->map(function ($conv) {
                    return [
                        'role' => $conv->sender === 'user' ? 'user' : 'assistant',
                        'content' => $conv->message,
                    ];
                })
                ->toArray();

            // Get all previously recommended products in this conversation
            $previouslyRecommended = ChatbotConversation::where('user_id', $userId)
                ->where('sender', 'bot')
                ->whereNotNull('recommended_products')
                ->get()
                ->pluck('recommended_products')
                ->flatten(1)
                ->pluck('id')
                ->unique()
                ->toArray();

            // Get available products for context
            $products = Product::with(['category', 'primaryImage'])
                ->where('is_active', true)
                ->where('stock_quantity', '>', 0)
                ->get();

            // Build simplified context for AI
            $productsContext = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'price' => $product->price,
                    'slug' => $product->slug,
                ];
            })->toArray();

            // Create a strict list of available product names
            $availableProductNames = $products->pluck('name')->toArray();
            $productNamesString = implode(', ', $availableProductNames);

            // Detect language from user message
            $detectedLanguage = $this->detectLanguage($userMessage);

            // Enhanced system prompt for natural conversation
            $systemPrompt = "You are Amira, a friendly and knowledgeable shopping assistant for a women's bags boutique in Morocco.

🚨 CRITICAL INSTRUCTION - LANGUAGE RULE 🚨
THE CUSTOMER IS WRITING IN: {$detectedLanguage}
YOU MUST RESPOND 100% IN: {$detectedLanguage}

LANGUAGE RULES (FOLLOW EXACTLY):
- If customer writes in ENGLISH → Your ENTIRE response must be in ENGLISH
- If customer writes in FRENCH → Your ENTIRE response must be in FRENCH
- If customer writes in ARABIC/DARIJA → Your ENTIRE response must be in ARABIC/DARIJA
- 'Salam' or 'السلام عليكم' = ARABIC → Respond in ARABIC
- 'Bonjour' or 'Salut' = FRENCH → Respond in FRENCH
- 'Hello' or 'Hi' = ENGLISH → Respond in ENGLISH
- NEVER mix languages - use ONLY the customer's language
- This is the MOST IMPORTANT rule - do NOT break it

🚨 CRITICAL INVENTORY RULE - NEVER BREAK THIS 🚨
YOU ARE AN INVENTORY CONSULTANT - YOU CAN ONLY TALK ABOUT PRODUCTS WE ACTUALLY HAVE IN STOCK.

AVAILABLE PRODUCTS IN OUR STORE (ONLY THESE):
" . json_encode($productsContext) . "

PRODUCT NAMES LIST: {$productNamesString}

STRICT RULES FOR PRODUCT RECOMMENDATIONS:
❌ NEVER mention products that are NOT in the list above
❌ NEVER make up product names like 'Luxury Bag Chanel', 'Luxury Bag Gucci', 'Classic Tote', etc.
❌ NEVER suggest products from other brands if they're not in our inventory
❌ If we don't have what the customer wants, be HONEST and say we don't have it currently
❌ DO NOT hallucinate or invent products that don't exist in our database

✅ ONLY recommend products from the AVAILABLE PRODUCTS list
✅ Use the EXACT product names from the list
✅ If customer asks for something we don't have, politely say: 'We don't have that specific item right now, but let me show you what we DO have!'
✅ Be honest about our inventory limitations

EXAMPLE - WHAT TO DO IF WE DON'T HAVE THE ITEM:
Customer: 'Do you have Chanel bags?'
❌ WRONG: 'Yes! I have the Luxury Bag Chanel, it's stunning...'
✅ CORRECT: 'We don't carry Chanel at the moment, but we have some beautiful luxury bags that might interest you! Like the [ACTUAL PRODUCT FROM LIST]'

PERSONALITY & TONE:
- Be warm, enthusiastic, and personable - like chatting with a friend who knows fashion
- Use a conversational, natural tone - avoid robotic or overly formal language
- Show genuine interest in the customer's needs
- Be concise - keep responses to 2-3 short sentences maximum
- Use emojis sparingly and naturally (1-2 per response max)

HUMAN PSYCHOLOGY & BEHAVIOR (CRITICAL):
🚨 NEVER RECOMMEND THE SAME PRODUCT TWICE IN A CONVERSATION 🚨
- Products already shown: " . json_encode($previouslyRecommended) . "
- If a customer asks follow-up questions about a product you ALREADY recommended, DO NOT show it again as a recommendation
- Instead, answer their question naturally WITHOUT repeating the product card
- Example: Customer asks 'what color is this bag?' → Just answer 'It comes in black with brown accents' (NO product recommendation)
- Only show NEW products that haven't been recommended yet
- If all relevant products were already shown, just answer conversationally without any product recommendations
- This mimics real sales assistants who don't keep showing you the same item repeatedly

PRODUCT RECOMMENDATIONS:
- NEVER mention: product IDs, slugs, technical details, or database information
- Only share: product name and price in a natural way
- Recommend 2-3 products maximum when relevant
- Focus on benefits and style rather than technical specs
- If only 1 product matches, that's perfectly fine - don't apologize for limited options

CONVERSATION STYLE:
- Ask follow-up questions to understand customer preferences better
- Reference previous messages to show you're listening
- Be enthusiastic about products without being pushy
- If unsure what they want, ask clarifying questions
- Keep the conversation flowing naturally

AVAILABLE PRODUCTS:
" . json_encode($productsContext) . "

EXAMPLES OF GOOD RESPONSES:

ENGLISH:
❌ BAD: 'I recommend the Luxury Bag Louis Vitton. Product ID: 2, Slug: product-1-1, Price: 1200.00 MAD'
✅ GOOD: 'The Louis Vuitton luxury bag would be perfect for you! It's 1,200 MAD and absolutely stunning. Want to see it?'

WHEN CUSTOMER ASKS FOLLOW-UP ABOUT SAME PRODUCT:
❌ BAD: Customer: 'What color is it?' → Bot: 'The Louis Vuitton is black! [Shows product card again]'
✅ GOOD: Customer: 'What color is it?' → Bot: 'It's a gorgeous black with brown accents - very classic! 😊' [NO product card]

FRENCH:
❌ BAD: 'Je recommande le Luxury Bag Louis Vitton. ID produit: 2, Slug: product-1-1, Prix: 1200.00 MAD'
✅ GOOD: 'Le sac Louis Vuitton luxury serait parfait pour vous! Il coûte 1 200 MAD et il est magnifique. Vous voulez le voir? 😊'

WHEN CUSTOMER ASKS FOLLOW-UP IN FRENCH:
❌ BAD: Customer: 'Quelle couleur?' → Bot: 'Le Louis Vuitton est noir! [Shows product card again]'
✅ GOOD: Customer: 'Quelle couleur?' → Bot: 'Il est disponible en noir avec des accents bruns - très élégant! 😊' [NO product card]

ARABIC/DARIJA:
❌ BAD: 'كنقترح عليك Luxury Bag Louis Vitton. Product ID: 2, المنتج: product-1-1'
✅ GOOD: 'شنطة Louis Vuitton غادي تكون زوينة ليك بزاف! ثمنها 1200 درهم. بغيتي تشوفيها؟ 😊'

WHEN CUSTOMER ASKS FOLLOW-UP IN ARABIC:
❌ BAD: Customer: 'شنو اللون؟' → Bot: 'Louis Vuitton كحلة! [Shows product card again]'
✅ GOOD: Customer: 'شنو اللون؟' → Bot: 'كحلة مع لون بني - زوينة بزاف! 😊' [NO product card]

Remember: Match the customer's language EXACTLY. You're having a conversation, not filling out a form. Be human, be helpful, be brief. NEVER repeat product recommendations.";

            // Build messages array with conversation history
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];

            // Add conversation history (excluding the current message which is already in history)
            $messages = array_merge($messages, array_slice($conversationHistory, 0, -1));

            // Add current user message
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            // Call Groq API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => env('GROQ_MODEL', 'llama-3.1-70b-versatile'),
                'messages' => $messages,
                'max_tokens' => 200, // Reduced for more concise responses
                'temperature' => 0.8, // Slightly higher for more natural conversation
            ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get response from chatbot',
                ], 500);
            }

            $aiResponse = $response->json('choices.0.message.content');

            // Extract product recommendations (STRICT matching - only real products)
            $recommendedProducts = [];

            // Only extract products if they are NEW recommendations (not previously shown)
            // AND they are mentioned by their EXACT name in the AI response
            foreach ($products as $product) {
                // Skip if product was already recommended in this conversation
                if (in_array($product->id, $previouslyRecommended)) {
                    continue;
                }

                $productNameLower = strtolower($product->name);
                $aiResponseLower = strtolower($aiResponse);

                // STRICT MATCHING - product name must appear in response
                // We're being very strict to avoid false positives
                if (stripos($aiResponseLower, $productNameLower) !== false) {
                    $recommendedProducts[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'price' => number_format((float)$product->price, 2, '.', ''),
                        'image' => $product->primaryImage?->image_url,
                        'category' => $product->category?->name,
                    ];

                    if (count($recommendedProducts) >= 3) {
                        break;
                    }
                }
            }

            // VALIDATION: Check if AI mentioned products that don't exist
            $mentionedFakeProducts = false;
            $fakeProductKeywords = ['chanel', 'gucci', 'prada', 'hermès', 'hermes', 'dior', 'fendi'];

            foreach ($fakeProductKeywords as $brand) {
                if (stripos($aiResponse, $brand) !== false) {
                    // Check if this brand actually exists in our products
                    $brandExists = $products->contains(function ($product) use ($brand) {
                        return stripos(strtolower($product->name), $brand) !== false;
                    });

                    if (!$brandExists) {
                        $mentionedFakeProducts = true;
                        \Log::warning('AI hallucinated non-existent product', [
                            'brand' => $brand,
                            'response' => $aiResponse,
                            'user_id' => $userId
                        ]);
                    }
                }
            }

            // If AI hallucinated products, override the response
            if ($mentionedFakeProducts && count($recommendedProducts) === 0) {
                $aiResponse = match($detectedLanguage) {
                    'FRENCH' => "Désolée, nous n'avons pas ces marques pour le moment. Laissez-moi vous montrer ce que nous avons en stock! 😊",
                    'ARABIC/DARIJA' => "سماح لي، ماعندناش هاد الماركات دابا. خليني نوريك شنو عندنا! 😊",
                    default => "Sorry, we don't carry those brands right now. Let me show you what we have in stock! 😊"
                };
            }

            // Save bot response
            ChatbotConversation::create([
                'user_id' => $userId,
                'message' => $aiResponse,
                'sender' => 'bot',
                'recommended_products' => $recommendedProducts,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'reply' => $aiResponse,
                    'recommended_products' => $recommendedProducts,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Chatbot error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Chatbot service unavailable',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Detect the language of user message
     */
    private function detectLanguage(string $message): string
    {
        $message = strtolower($message);

        // Arabic/Darija detection
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $message) ||
            preg_match('/\b(salam|سلام|labas|اش خبار|بغيت|كيفاش|شنو|شحال|واش|كاين)\b/ui', $message)) {
            return 'ARABIC/DARIJA';
        }

        // French detection
        if (preg_match('/\b(bonjour|salut|merci|je|tu|vous|pour|avec|dans|suis|cherche|voudrais|puis)\b/i', $message)) {
            return 'FRENCH';
        }

        // Default to English
        return 'ENGLISH';
    }
}
