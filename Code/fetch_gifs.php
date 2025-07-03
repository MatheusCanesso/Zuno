<?php
require_once 'Configs/config.php'; // Para acessar as chaves de API

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$limit = 20; // Número de GIFs a serem retornados
$offset = $_GET['offset'] ?? 0;

$gifs = [];

if (!empty($query)) {
    // Buscar no Giphy (Trending ou Search)
    $giphyUrl = 'https://api.giphy.com/v1/gifs/search?api_key=' . GIPHY_API_KEY . '&q=' . urlencode($query) . '&limit=' . $limit . '&offset=' . $offset;
    $giphyResponse = @file_get_contents($giphyUrl); // @ para suprimir warnings em caso de erro HTTP
    if ($giphyResponse) {
        $giphyData = json_decode($giphyResponse, true);
        if (isset($giphyData['data'])) {
            foreach ($giphyData['data'] as $gif) {
                // Use a URL de 'fixed_height' ou 'original' dependendo da qualidade/tamanho desejado
                if (isset($gif['images']['original']['url'])) {
                    $gifs[] = ['url' => $gif['images']['original']['url'], 'source' => 'Giphy'];
                }
            }
        }
    }

    // Buscar no Tenor (Trending ou Search)
    // Nota: Tenor API v2 pode ter algumas diferenças, este é um exemplo básico
    $tenorUrl = 'https://api.tenor.com/v1/search?key=' . TENOR_API_KEY . '&q=' . urlencode($query) . '&limit=' . $limit . '&pos=' . $offset;
    $tenorResponse = @file_get_contents($tenorUrl);
    if ($tenorResponse) {
        $tenorData = json_decode($tenorResponse, true);
        if (isset($tenorData['results'])) {
            foreach ($tenorData['results'] as $gif) {
                // Tenor geralmente tem várias URLs de mídia, escolha uma, ex: 'gif' ou 'mediumgif'
                if (isset($gif['media'][0]['gif']['url'])) {
                    $gifs[] = ['url' => $gif['media'][0]['gif']['url'], 'source' => 'Tenor'];
                }
            }
        }
    }
} else {
    // Buscar GIFs em alta (trending) se a busca estiver vazia
    $giphyUrl = 'https://api.giphy.com/v1/gifs/trending?api_key=' . GIPHY_API_KEY . '&limit=' . $limit . '&offset=' . $offset;
    $giphyResponse = @file_get_contents($giphyUrl);
    if ($giphyResponse) {
        $giphyData = json_decode($giphyResponse, true);
        if (isset($giphyData['data'])) {
            foreach ($giphyData['data'] as $gif) {
                if (isset($gif['images']['original']['url'])) {
                    $gifs[] = ['url' => $gif['images']['original']['url'], 'source' => 'Giphy'];
                }
            }
        }
    }
    // Tenor trending
    $tenorUrl = 'https://api.tenor.com/v1/trending?key=' . TENOR_API_KEY . '&limit=' . $limit . '&pos=' . $offset;
    $tenorResponse = @file_get_contents($tenorUrl);
    if ($tenorResponse) {
        $tenorData = json_decode($tenorResponse, true);
        if (isset($tenorData['results'])) {
            foreach ($tenorData['results'] as $gif) {
                if (isset($gif['media'][0]['gif']['url'])) {
                    $gifs[] = ['url' => $gif['media'][0]['gif']['url'], 'source' => 'Tenor'];
                }
            }
        }
    }
}

// Embaralha e limita para ter uma mistura de Giphy e Tenor, se ambos retornarem
shuffle($gifs);
$finalGifs = array_slice($gifs, 0, $limit);

echo json_encode(['data' => $finalGifs]);
?>