<?php
// Define the base URL for the sitemap
$baseUrl = 'https://teia.art/';

// Create a new DOM document
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

// Create the root <urlset> element
$urlset = $dom->createElement('urlset');
$urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
$dom->appendChild($urlset);

// Fetch the tokens from the GraphQL endpoint
$query = 'query sitemap @cached(ttl: 3600) {
    tokens {
        token_id
    }
}';

$curl = curl_init(getenv('TEZTOK_URL'));
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-hasura-admin-secret: ' . getenv('TEZTOK_KEY')]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
$response = curl_exec($curl);
curl_close($curl);

if ($response) {
    $data = json_decode($response, true);

    if (isset($data['data']['tokens'])) {
        // Iterate over the tokens and create <url> elements
        foreach ($data['data']['tokens'] as $token) {
            $url = $dom->createElement('url');

            $loc = $dom->createElement('loc', $baseUrl . 'objkt/' . $token['token_id']);
            $url->appendChild($loc);

            $urlset->appendChild($url);
        }
    }
}

// Save the XML document to a file
echo $dom->saveXML();