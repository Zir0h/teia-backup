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

if (isset($_GET['address']) && strlen($_GET['address']) === 36) {
  $address = $_GET['address'];
} else {
  die('Please specify a valid Tezos wallet address');
}

$url = "https://teztok.teia.rocks/v1/graphql";
$teiaOnly = true;
if (isset($_GET['includeNonTEIA'])) {
  $teiaOnly = false;
  $url = "https://api.teztok.com/v1/graphql";
}

$useCAR = false;
if (isset($_GET['useCAR'])) {
  $useCAR = 1;
}

$asJSON = false;
if (isset($_GET['asJSON'])) {
  $asJSON = 1;
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

function getDownloadLink($cid, $type, $platform, $format, &$found, $useCAR = false, $asJSON = false)
{
  $cid = str_replace('ipfs://', '', explode('/', $cid)[2]);
  if (isset($found[$cid])) {
    return false;
  }

  $found[$cid] = 1;
  if ($useCAR) {
    $ext = 'car';
  } else {
    $ext = mime2ext($type);
    if (isset($format['mime_type'])) {
      $ext = mime2ext($format['mime_type']);
    }
    if ($ext === false) {
      $ext = 'tar';
    }
  }

  $filename = $cid . '.' . $ext;
  if (!$useCAR && isset($format['file_name'])) {
    $filename = str_replace('ipfs://', '', $format['file_name']);
  }

  $gateway = 'nftstorage.link';
  if ($platform === 'HEN') {
    $gateway = 'cache.teia.rocks';
  }

  $url = "https://{$gateway}/ipfs/{$cid}?download=true&format={$ext}&filename={$filename}";

  if($asJSON) {
    return array('cid' => $cid, 'url' => $url);
  }

  return "<a href=\"{$url}\">{$url}</a><br />";
}

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
  "Content-Type: application/json",
  ($teiaOnly ? 'x-hasura-admin-secret: ' . getenv('TEZTOK_KEY') : ''),
));
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($curl);
$links = array();
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
        if ($link = getDownloadLink($format['uri'], $token['token']['mime_type'], $token['token']['platform'], $format, $found, $useCAR, $asJSON)) {
          $links[] = $link;
        }
      }
    }
  }
  curl_close($curl);
}

if($asJSON) {
  header('Content-Type: application/json');
  echo json_encode($links);
  exit;
}


?>
<!doctype html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
    integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

  <title>Backup your Tezos NFT's</title>
</head>

<body>
  <div class="container-fluid">
    <h1>Backup your Tezos NFT's</h1>
    <p>This page is meant to be used in combination with <a href="https://www.downthemall.net/">DownThemAll</a>. Install
      the extension and add the following links to the download queue:</p>
    <?php
    $amount = count($links);
    if ($amount > 0) {
      echo "<p>Found {$amount} tokens in {$address}.</p>";
      foreach ($links as $link) {
        echo $link;
      }
    } else {
      echo "<p>This wallet does not contain any tokens.</p>";
    }
    ?>
  </div>
  <!-- Optional JavaScript -->
  <!-- jQuery first, then Popper.js, then Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
    integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
    crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.7/dist/umd/popper.min.js"
    integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1"
    crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.min.js"
    integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM"
    crossorigin="anonymous"></script>
</body>

</html>