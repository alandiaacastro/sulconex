<?php

class GoogleMapsUrlBuilder
{
    /**
     * Gera URL do Google Maps para visualizar uma localização
     */
    public static function getLocationUrl(string $city): string
    {
        $encoded = urlencode($city);
        return "https://maps.google.com/?q=$encoded&z=10";
    }

    /**
     * Gera URL do Google Maps com coordenadas
     */
    public static function getCoordinatesUrl(float $lat, float $lon, string $label = ''): string
    {
        if (!empty($label)) {
            $encoded = urlencode($label);
            return "https://maps.google.com/?q=$encoded@$lat,$lon&z=10";
        }
        return "https://maps.google.com/?q=$lat,$lon&z=10";
    }

    /**
     * Gera URL do Google Maps para traçar rota entre múltiplos pontos
     *
     * @param array $waypoints Array de ['city' => 'name'] ou ['lat' => x, 'lon' => y, 'label' => 'name']
     */
    public static function getRouteUrl(array $waypoints): string
    {
        if (empty($waypoints)) {
            return "https://maps.google.com";
        }

        $parts = [];
        foreach ($waypoints as $point) {
            if (isset($point['lat']) && isset($point['lon'])) {
                // Coordenadas
                $parts[] = $point['lat'] . ',' . $point['lon'];
            } elseif (isset($point['city'])) {
                // Nome da cidade
                $parts[] = urlencode($point['city']);
            } elseif (is_string($point)) {
                // String simples
                $parts[] = urlencode($point);
            }
        }

        if (empty($parts)) {
            return "https://maps.google.com";
        }

        // Mínimo 2 pontos para rota
        if (count($parts) < 2) {
            return self::getCoordinatesUrl(0, 0, $parts[0]);
        }

        // Google Maps directions URL
        $route = implode('/', $parts);
        return "https://maps.google.com/maps/dir/$route";
    }

    /**
     * Gera URL da rota completa a partir de eventos
     */
    public static function getRouteFromEvents(array $eventos): string
    {
        if (empty($eventos)) {
            return "https://maps.google.com";
        }

        $waypoints = [];
        foreach ($eventos as $evento) {
            $location = self::extractLocation($evento);
            if (!empty($location)) {
                $waypoints[] = $location;
            }
        }

        return self::getRouteUrl($waypoints);
    }

    /**
     * Extrai localização de um evento
     */
    private static function extractLocation($evento): ?string
    {
        // Tenta cidade do demora field
        if (!empty($evento->demora)) {
            $demora = (string) $evento->demora;
            $parts = explode('|', $demora);
            if (!empty($parts[0])) {
                return trim($parts[0]);
            }
        }

        // Tenta localizacao field
        if (!empty($evento->localizacao)) {
            return (string) $evento->localizacao;
        }

        return null;
    }

    /**
     * Gera URL para buscar uma localização
     */
    public static function getSearchUrl(string $query): string
    {
        return "https://maps.google.com/?q=" . urlencode($query);
    }

    /**
     * Retorna iframe do Google Maps (alternativa)
     */
    public static function getIframeUrl(float $lat, float $lon, string $label = '', int $zoom = 10): string
    {
        $encoded = urlencode($label ?: "$lat,$lon");
        return "https://www.google.com/maps/embed/v1/place?key=AIzaSyDummyKey&q=$lat,$lon&zoom=$zoom";
        // Nota: Requer API Key para iframe
    }

    /**
     * Retorna URL direto do mapa estático (imagem)
     */
    public static function getStaticMapUrl(float $lat, float $lon, int $zoom = 10, int $width = 600, int $height = 400): string
    {
        return "https://maps.googleapis.com/maps/api/staticmap?center=$lat,$lon&zoom=$zoom&size={$width}x{$height}&markers=$lat,$lon";
        // Nota: Requer API Key
    }
}
