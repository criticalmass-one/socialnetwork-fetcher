<?php declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Client;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Nimmt eine yt-dlp-cookies.txt (Netscape-Format) entgegen und legt sie unter
 * dem konfigurierten Pfad (YT_DLP_COOKIES_FILE) ab. Damit kann z. B. eine
 * Browser-Extension die Instagram-Session-Cookies direkt hochladen, ohne dass
 * jemand manuell eine Datei per scp auf den Server kopieren muss.
 *
 * Auth: liegt unter /api → durch den ApiTokenAuthenticator (Bearer) geschützt
 * (access_control: ^/api → ROLE_API_CLIENT).
 */
class CookieController
{
    public function __construct(
        private readonly Security $security,
        private readonly string $ytDlpCookiesFile = '',
    ) {
    }

    #[Route('/api/yt-dlp-cookies', name: 'app_api_ytdlp_cookies', methods: ['PUT', 'POST'])]
    #[OA\Put(
        summary: 'Speichert eine yt-dlp cookies.txt (Netscape) für medienpflichtige Netzwerke (z. B. Instagram-Login).',
        responses: [
            new OA\Response(response: 200, description: 'Gespeichert.'),
            new OA\Response(response: 400, description: 'Body ist keine gültige Netscape-cookies.txt.'),
            new OA\Response(response: 401, description: 'Fehlender/ungültiger Bearer-Token.'),
            new OA\Response(response: 503, description: 'YT_DLP_COOKIES_FILE nicht konfiguriert.'),
        ],
    )]
    public function store(Request $request): Response
    {
        $this->requireClient();

        if ($this->ytDlpCookiesFile === '') {
            return new JsonResponse(['error' => 'YT_DLP_COOKIES_FILE ist nicht konfiguriert.'], 503);
        }

        $content = $request->getContent();

        // JSON-Variante {"cookies":"..."} ebenfalls erlauben.
        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_starts_with(trim($contentType), 'application/json')) {
            $data = json_decode($content, true);
            $content = (is_array($data) && isset($data['cookies'])) ? (string) $data['cookies'] : '';
        }

        $content = trim($content);

        // Grobe Plausibilitätsprüfung: Netscape-cookies.txt sind Tab-getrennt.
        if (strlen($content) < 20 || !str_contains($content, "\t")) {
            return new JsonResponse(
                ['error' => 'Body sieht nicht nach einer Netscape-cookies.txt aus (Tab-getrennte Zeilen erwartet).'],
                400,
            );
        }

        if (!str_starts_with($content, '# Netscape HTTP Cookie File')) {
            $content = "# Netscape HTTP Cookie File\n" . $content;
        }
        $content .= "\n";

        $dir = \dirname($this->ytDlpCookiesFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return new JsonResponse(['error' => 'Cookies-Verzeichnis nicht beschreibbar.'], 500);
        }

        // Atomar schreiben (temp + rename), restriktive Rechte (Session-Datei!).
        $tmp = $this->ytDlpCookiesFile . '.tmp';
        if (@file_put_contents($tmp, $content) === false) {
            return new JsonResponse(['error' => 'Schreiben der Cookies-Datei fehlgeschlagen.'], 500);
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $this->ytDlpCookiesFile)) {
            @unlink($tmp);
            return new JsonResponse(['error' => 'Verschieben der Cookies-Datei fehlgeschlagen.'], 500);
        }

        return new JsonResponse([
            'status' => 'ok',
            'path'   => $this->ytDlpCookiesFile,
            'bytes'  => strlen($content),
            'cookies' => max(0, substr_count($content, "\n") - 1),
        ]);
    }

    private function requireClient(): Client
    {
        $client = $this->security->getUser();
        if (!$client instanceof Client) {
            throw new AccessDeniedHttpException();
        }

        return $client;
    }
}
