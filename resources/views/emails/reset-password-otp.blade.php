<!-- resources/views/emails/reset-password-otp.blade.php -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background-color: #171717;
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }

        .email-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .email-body {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #171717;
        }

        .message {
            font-size: 15px;
            color: #525252;
            margin-bottom: 30px;
            line-height: 1.7;
        }

        .otp-container {
            background-color: #fafafa;
            border: 2px dashed #d4d4d4;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }

        .otp-label {
            font-size: 13px;
            color: #737373;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .otp-code {
            font-size: 42px;
            font-weight: 700;
            color: #171717;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }

        .otp-validity {
            font-size: 13px;
            color: #737373;
            margin-top: 15px;
        }

        .warning {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }

        .warning-text {
            font-size: 14px;
            color: #991b1b;
            line-height: 1.6;
        }

        .email-footer {
            background-color: #fafafa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e5e5;
        }

        .footer-text {
            font-size: 13px;
            color: #737373;
            line-height: 1.6;
        }

        .footer-link {
            color: #171717;
            text-decoration: none;
            font-weight: 600;
        }

        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }

            .email-header {
                padding: 30px 20px;
            }

            .email-header h1 {
                font-size: 24px;
            }

            .email-body {
                padding: 30px 20px;
            }

            .otp-code {
                font-size: 36px;
                letter-spacing: 6px;
            }

            .email-footer {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Réinitialisation de mot de passe</h1>
        </div>

        <div class="email-body">
            <div class="greeting">
                Bonjour {{ $userName }},
            </div>

            <div class="message">
                Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.
                Utilisez le code de vérification ci-dessous pour réinitialiser votre mot de passe.
            </div>

            <div class="otp-container">
                <div class="otp-label">Votre code de vérification</div>
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-validity">Ce code expire dans 15 minutes</div>
            </div>

            <div class="message">
                Entrez ce code sur la page de réinitialisation du mot de passe pour continuer.
            </div>

            <div class="warning">
                <div class="warning-text">
                    <strong>⚠️ Important :</strong> Si vous n'avez pas demandé cette réinitialisation,
                    veuillez ignorer cet email. Votre mot de passe reste sécurisé et aucune modification ne sera effectuée.
                </div>
            </div>
        </div>

        <div class="email-footer">
            <div class="footer-text">
                Cet email a été envoyé par <a href="{{ config('app.url') }}" class="footer-link">{{ config('app.name') }}</a>
                <br>
                © {{ date('Y') }} Tous droits réservés
            </div>
        </div>
    </div>
</body>
</html>
