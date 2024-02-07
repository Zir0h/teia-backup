<?php
function mime2ext($mime)
{
  $mime_map = [
    "application/pdf" => "pdf",
    "application/x-directory" => "tar",
    "application/zip" => "zip",
    "audio/flac" => "flac",
    "audio/mpeg" => "mp3",
    "audio/ogg" => "ogg",
    "audio/wav" => "wav",
    "audio/x-wav" => "wav",
    "image/bmp" => "bmp",
    "image/gif" => "gif",
    "image/jpeg" => "jpeg",
    "image/jpg" => "jpg",
    "image/png" => "png",
    "image/RAF" => "raf",
    "image/svg+xml" => "svg",
    "image/tiff" => "tiff",
    "image/webp" => "webp",
    "model/gltf-binary" => "glb",
    "model/gltf+json" => "gltf",
    "text/html" => "html",
    "text/javascript" => "js",
    "text/markdown" => "md",
    "video/avi" => "avi",
    "video/mp4" => "mp4",
    "video/ogg" => "ogg",
    "video/quicktime" => "mov",
    "video/webm" => "webm",
    "video/x-matroska" => "mkv",
    "video/x-ms-wmv" => "wmv",
  ];

  return isset($mime_map[$mime]) === true ? $mime_map[$mime] : false;
}

if(!isset($_GET['address'])) {
  die('Please specify a wallet address in the url using ?address=sometezosaddress');
}

$url = "https://teztok.teia.rocks/v1/graphql";
$data = array(
  "query" => "
      fragment baseTokenFields on tokens {
        artifact_uri
        mime_type
        name
        token_id
      }

      query collectorGallery(\$address: String!) {
        holdings(
          where: {
            holder_address: { _eq: \$address }
            token: {
              artist_address: { _neq: \$address }
              metadata_status: { _eq: \"processed\" }
            }
            amount: { _gt: \"0\" }
          }
          order_by: { last_received_at: desc }
        ) {
          token {
            ...baseTokenFields
          }
        }
      }
    ",
  "variables" => array("address" => $_GET['address']),
  "operationName" => "collectorGallery"
);

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($curl);
if ($response === false) {
  // Handle error
  $error = curl_error($curl);
  curl_close($curl);
  // Handle the error
} else {
  // Handle response
  $responseData = json_decode($response, true);
  foreach ($responseData['data']['holdings'] as $token) {
    $ext = mime2ext($token['token']['mime_type']);
    if($ext === false) {
      $ext = 'tar';
    }
    $url = 'https://cache.teia.rocks/ipfs/' . str_replace('ipfs://', '', $token['token']['artifact_uri']) . '?download=true&format=' . $ext;
    echo '<a href="' . $url . '">' . $url . '</a><br />';
  }
  curl_close($curl);
}