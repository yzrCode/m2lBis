<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as ErreurCourriel;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

//Service qui envoie les accès temporaires aux nouveaux licenciés avec le serveur SMTP du club.
final class ServiceCourriel
{
    public static function verifierConfiguration(): void
    {
        //La configuration et le client mail sont préparés avant de tenter une connexion.
        $courriel = self::preparerCourriel();
        try {
            //La vérification confirme que le serveur SMTP est joignable avant la création des comptes.
            if (!$courriel->smtpConnect()) {
                throw new RuntimeException('Impossible de se connecter au serveur SMTP configuré.');
            }
        } catch (ErreurCourriel $erreur) {
            //Le message transmis à l'application reste compréhensible sans exposer les paramètres SMTP.
            throw new RuntimeException(
                'La connexion SMTP a échoué. Vérifiez les paramètres de .env et réessayez.',
                0,
                $erreur
            );
        } finally {
            //La connexion de contrôle est fermée même si la tentative échoue.
            $courriel->smtpClose();
        }
    }

    public static function envoyerAcces(array $licencie, string $motDePasse): void
    {
        //La configuration est relue pour préparer le message avec les paramètres actuels.
        $configuration = self::configuration();
        $courriel = self::preparerCourriel();
        //L'expéditeur est défini depuis la configuration et le destinataire depuis la fiche du licencié.
        $courriel->setFrom($configuration['from'], $configuration['name']);
        $courriel->addAddress($licencie['email'], $licencie['prenom'] . ' ' . $licencie['nom']);
        $courriel->isHTML(true);
        $courriel->Subject = 'Votre compte licencié M2L Football Club';

        //Les valeurs dynamiques sont échappées avant d'être insérées dans le contenu HTML.
        $prenom = htmlspecialchars($licencie['prenom'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $email = htmlspecialchars($licencie['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $motDePasseEchappe = htmlspecialchars($motDePasse, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lienConnexion = htmlspecialchars($configuration['app_url'] . '/connexion', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        //Le message HTML est destiné aux logiciels de messagerie qui affichent la mise en forme.
        $courriel->Body = "<p>Bonjour {$prenom},</p>"
            . '<p>Votre compte licencié M2L Football Club a été créé.</p>'
            . "<p>Adresse de connexion : <strong>{$email}</strong><br>"
            . "Mot de passe provisoire : <strong>{$motDePasseEchappe}</strong></p>"
            . '<p>Connexion : <a href="' . $lienConnexion . '">' . $lienConnexion . '</a></p>'
            . '<p>Vous devrez choisir un nouveau mot de passe lors de votre première connexion.</p>';
        //La version texte permet de lire le même message dans les logiciels sans affichage HTML.
        $courriel->AltBody = "Bonjour {$licencie['prenom']},\n\n"
            . "Votre compte licencié M2L Football Club a été créé.\n"
            . "Adresse de connexion : {$licencie['email']}\n"
            . "Mot de passe provisoire : {$motDePasse}\n\n"
            . "Connexion : {$configuration['app_url']}/connexion\n\n"
            . "Vous devrez choisir un nouveau mot de passe lors de votre première connexion.";
        //PHPMailer envoie le message et signale une erreur si le serveur le refuse.
        $courriel->send();
    }

    private static function preparerCourriel(): PHPMailer
    {
        //Cette méthode centralise la création et les réglages du client PHPMailer.
        $configuration = self::configuration();
        $courriel = new PHPMailer(true);
        //Les identifiants et le serveur sont chargés depuis la configuration SMTP.
        $courriel->isSMTP();
        $courriel->Host = $configuration['host'];
        $courriel->SMTPAuth = true;
        $courriel->Timeout = 15;
        $courriel->Username = $configuration['username'];
        $courriel->Password = $configuration['password'];
        $courriel->Port = $configuration['port'];
        //Le chiffrement dépend du réglage tls ou ssl fourni dans le fichier .env.
        $courriel->SMTPSecure = $configuration['encryption'] === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $courriel->CharSet = PHPMailer::CHARSET_UTF8;

        return $courriel;
    }

    private static function configuration(): array
    {
        //Les valeurs sont lues depuis les variables chargées au démarrage de l'application.
        $urlApplication = rtrim(trim((string) ($_ENV['APP_URL'] ?? '')), '/');
        $partiesUrl = parse_url($urlApplication);
        //Les valeurs reçues sont nettoyées ou converties avant leur utilisation.
        $configuration = [
            'host' => trim((string) ($_ENV['SMTP_HOST'] ?? '')),
            'port' => filter_var($_ENV['SMTP_PORT'] ?? null, FILTER_VALIDATE_INT),
            'username' => trim((string) ($_ENV['SMTP_USERNAME'] ?? '')),
            'password' => (string) ($_ENV['SMTP_PASSWORD'] ?? ''),
            'encryption' => strtolower(trim((string) ($_ENV['SMTP_ENCRYPTION'] ?? 'tls'))),
            'from' => trim((string) ($_ENV['SMTP_FROM_ADDRESS'] ?? '')),
            'name' => trim((string) ($_ENV['SMTP_FROM_NAME'] ?? 'M2L Football Club')),
            'app_url' => $urlApplication,
        ];

        //Tous les champs requis sont vérifiés pour éviter un envoi mal configuré.
        if (
            $configuration['host'] === ''
            || $configuration['port'] === false
            || $configuration['port'] < 1
            || $configuration['port'] > 65535
            || $configuration['username'] === ''
            || $configuration['password'] === ''
            || !in_array($configuration['encryption'], ['tls', 'ssl'], true)
            || !filter_var($configuration['from'], FILTER_VALIDATE_EMAIL)
            || filter_var($configuration['app_url'], FILTER_VALIDATE_URL) === false
            || !is_array($partiesUrl)
            || !in_array(strtolower((string) ($partiesUrl['scheme'] ?? '')), ['http', 'https'], true)
            || empty($partiesUrl['host'])
        ) {
            throw new RuntimeException(
                'L’envoi des accès n’est pas configuré. Renseignez SMTP et APP_URL dans le fichier .env.'
            );
        }

        //La configuration validée peut maintenant être utilisée pour connecter PHPMailer.
        return $configuration;
    }
}
