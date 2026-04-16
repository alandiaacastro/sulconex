<?php

class FollowUpService
{
    public static function getEventosTimeline(Conhecimento $crt, string $statusNome): array
    {
        $eventos = [];

        if (!empty($crt->data_transportador_assinatura)) {
            $eventos[] = [
                'ts' => strtotime((string) $crt->data_transportador_assinatura . ' 12:00:00') ?: 0,
                'data' => self::formatDateTime((string) $crt->data_transportador_assinatura . ' 12:00:00'),
                'texto' => 'CRT emitido / assinado pelo transportador.',
                'hl' => true,
            ];
        }

        $eventos[] = [
            'ts' => time(),
            'data' => date('d/m/Y H\hi'),
            'texto' => 'Status atual: ' . $statusNome . '.',
            'hl' => false,
        ];

        $eventos = array_merge($eventos, self::parseNotesToEvents((string) ($crt->observacoes ?? '')));
        $eventos = array_merge($eventos, self::parseNotesToEvents((string) ($crt->documentos_anexos ?? '')));

        usort($eventos, function ($a, $b) {
            return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
        });

        $parsedEvents = [];
        foreach ($eventos as $i => $evento) {
            $rawText = (string) ($evento['texto'] ?? '');
            $hl = ($i === 0 || !empty($evento['hl']));

            [$title, $sub] = self::splitTimelineText($rawText);
            $icon = self::pickTimelineIcon($title . ' ' . $sub);

            $parsedEvents[] = [
                'ts' => $evento['ts'],
                'data' => $evento['data'],
                'texto' => $rawText,
                'title' => $title,
                'sub' => $sub,
                'icon' => $icon,
                'highlight' => $hl,
            ];
        }

        return $parsedEvents;
    }

    public static function splitTimelineText(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['-', ''];
        }

        if (preg_match('/^(.+?):\s*(.+)$/', $texto, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        if (preg_match('/^(.+?\.)\s+(.+)$/', $texto, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$texto, ''];
    }

    public static function pickTimelineIcon(string $texto): string
    {
        $t = strtolower($texto);

        if (strpos($t, 'entreg') !== false) {
            return 'fas fa-check';
        }

        if (strpos($t, 'transit') !== false || strpos($t, 'rota') !== false) {
            return 'fas fa-truck';
        }

        if (strpos($t, 'aguard') !== false) {
            return 'fas fa-clock';
        }

        if (strpos($t, 'post') !== false || strpos($t, 'emitido') !== false || strpos($t, 'assinado') !== false) {
            return 'fas fa-file-alt';
        }

        if (strpos($t, 'status atual') !== false) {
            return 'fas fa-info-circle';
        }

        return 'fas fa-circle';
    }

    public static function parseNotesToEvents(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        $linhas = preg_split('/\\r\\n|\\r|\\n/', $texto) ?: [];
        $eventos = [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }

            $data = date('d/m/Y H\\hi');
            $ts = time() - 60;
            $mensagem = $linha;

            if (preg_match('/^(\\d{2}\\/\\d{2}\\/\\d{4})(?:\\s+(\\d{2}[:h]\\d{2}))?\\s*[-:]\\s*(.+)$/', $linha, $m)) {
                $hora = !empty($m[2]) ? str_replace('h', ':', $m[2]) : '12:00';
                $raw = $m[1] . ' ' . $hora;
                $parsed = strtotime(str_replace('/', '-', $raw));
                if ($parsed) {
                    $ts = $parsed;
                    $data = date('d/m/Y H\\hi', $parsed);
                }
                $mensagem = trim($m[3]);
            }

            $eventos[] = [
                'ts' => $ts,
                'data' => $data,
                'texto' => $mensagem,
                'hl' => false
            ];
        }

        return $eventos;
    }

    public static function formatDate(string $value): string
    {
        if (!$value) {
            return '';
        }

        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
            return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
        }

        return $value;
    }

    public static function formatDateTime(string $value): string
    {
        $ts = strtotime($value);
        if ($ts) {
            return date('d/m/Y H\\hi', $ts);
        }

        return $value;
    }
}
