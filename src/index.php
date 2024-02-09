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

if (!isset($_GET['address'])) {
  die('Please specify a wallet address in the url using ?address=sometezosaddress');
}

$url = "https://teztok.teia.rocks/v1/graphql";
if(isset($_GET['includeNonTEIA'])) {
  $url = "https://api.teztok.com/v1/graphql";
}

$data = array(
  "query" => "
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
        ) {
          token {
            mime_type
            name
            token_id
            formats
            platform
          }
        }
      }
    ",
  "variables" => array("address" => $_GET['address']),
  "operationName" => "collectorGallery"
);

function getDownloadLink($cid, $type, $platform, $format, &$found)
{
  $cid = str_replace('ipfs://', '', $cid);
  if (isset($found[$cid])) {
    return false;
  }

  $found[$cid] = 1;

  $ext = mime2ext($type);
  if ($ext === false) {
    $ext = 'tar';
  }

  $filename = str_replace('ipfs://', '', $format['uri']) . '.' . $ext;
  if (isset($format['file_name'])) {
    $filename = str_replace('ipfs://', '', $format['file_name']);
  }

  $gateway = 'nftstorage.link';
  if($platform === 'HEN') {
    $gateway = 'cache.teia.rocks';
  }

  $url = 'https://' . $gateway . '/ipfs/' . $cid . '?download=true&format=' . $ext . '&filename=' . $filename;
  return '<a href="' . $url . '">' . $url . '</a><br />';
}

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
  $found = array();
  foreach ($responseData['data']['holdings'] as $token) {
    if (isset($token['token']['formats'])) {
      foreach ($token['token']['formats'] as $format) {
        if ($link = getDownloadLink($format['uri'], $token['token']['mime_type'], $token['token']['platform'], $format, $found)) {
          echo $link;
        }
      }
    }
  }
  curl_close($curl);
}
