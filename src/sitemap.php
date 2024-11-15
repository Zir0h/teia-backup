<?php
header('Content-Type: application/xml');

if (file_exists('/var/www/sitemap/sitemap.xml') && time() - filemtime('/var/www/sitemap/sitemap.xml') < getenv('REFRESH_INTERVAL')) {
    // Sitemap was generated less than 24 hours ago, return its content
    $sitemap = file_get_contents('/var/www/sitemap/sitemap.xml');
    echo $sitemap;
    exit;
}

// Create a new DOM document
$sitemap = new DOMDocument('1.0', 'UTF-8');
$sitemap->formatOutput = false;

// Create the root <sitemapindex> element
$sitemapindex = $sitemap->createElement('sitemapindex');
$sitemapindex->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
$sitemap->appendChild($sitemapindex);

// Fetch the tokens from the GraphQL endpoint
$query = 'query sitemap {
    tokens {
        token_id
    }
    teia_users {
        user_address
        name
    }
}';

$curl = curl_init(getenv('TEZTOK_URL'));
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    (getenv('TEZTOK_KEY') !== '' ? 'x-hasura-admin-secret: ' . getenv('TEZTOK_KEY') : ''),
]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
$response = curl_exec($curl);
curl_close($curl);

if ($response) {
    $data = json_decode($response, true);
    $total = count($data['data']['tokens']);
    for ($i = 0; $i <= $total; $i += getenv('SITEMAP_SIZE')) {
        // Create a new DOM document
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        // Create the root <urlset> element
        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $dom->appendChild($urlset);

        for ($j = $i; $j <= $i + getenv('SITEMAP_SIZE'); $j++) {
            if(isset($data['data']['tokens'][$j]['token_id'])) {
                $url = $dom->createElement('url');
                $loc = $dom->createElement('loc', getenv('BASE_URL') . '/objkt/' . $data['data']['tokens'][$j]['token_id']);
                $url->appendChild($loc);
                $urlset->appendChild($url);
            }
        }

        $dom->save('/var/www/sitemap/sitemap_tokens_' . $i . '.xml');

        $sub = $sitemap->createElement('sitemap');
        $loc = $sitemap->createElement('loc', getenv('SITEMAP_HOST') . '/sitemap/sitemap_tokens_' . $i . '.xml');
        $sub->appendChild($loc);
        $sitemapindex->appendChild($sub);
    }

    $total = count($data['data']['teia_users']);
    for ($i = 0; $i <= $total; $i += getenv('SITEMAP_SIZE')) {
        // Create a new DOM document
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        // Create the root <urlset> element
        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $dom->appendChild($urlset);

        for ($j = $i; $j <= $i + getenv('SITEMAP_SIZE'); $j++) {
            if(isset($data['data']['teia_users'][$j]['user_address'])) {
                $url = $dom->createElement('url');
                $loc = $dom->createElement('loc', getenv('BASE_URL') . '/tz/' . $data['data']['teia_users'][$j]['user_address']);
                $url->appendChild($loc);
                $urlset->appendChild($url);
            }
        }

        $dom->save('/var/www/sitemap/sitemap_users_' . $i . '.xml');

        $sub = $sitemap->createElement('sitemap');
        $loc = $sitemap->createElement('loc', getenv('SITEMAP_HOST') . '/sitemap/sitemap_users_' . $i . '.xml');
        $sub->appendChild($loc);
        $sitemapindex->appendChild($sub);
    }
}

// Save the XML document to a file
$sitemap->save('/var/www/sitemap/sitemap.xml');
echo $sitemap->saveXML();
