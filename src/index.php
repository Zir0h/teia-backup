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

$data = array(
  "query" => "
      query collectorGallery(\$address: String!) {
        holdings(
          where: {
            holder_address: { _eq: \$address }
            token: {
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
        $cid = str_replace('ipfs://', '', explode('/', $format['uri'])[2]);
        if (!isset($found[$cid])) {
          $ext = mime2ext($token['token']['mime_type']);
          if (isset($format['mime_type'])) {
            $ext = mime2ext($format['mime_type']);
          }
          if ($ext === false) {
            $ext = 'tar';
          }

          $filename = $cid . '.' . $ext;
          if (isset($format['file_name'])) {
            $filename = str_replace('ipfs://', '', $format['file_name']);
          }

          $gateway = 'nftstorage.link';
          if ($token['token']['platform'] === 'HEN') {
            $gateway = 'cache.teia.rocks';
          }

          $found[$cid] = array(
            'cid' => $cid,
            'car' => "https://{$gateway}/ipfs/{$cid}?format=car",
            'link' => "https://{$gateway}/ipfs/{$cid}?download=true&format={$ext}&filename={$filename}",
            'platform' => $token['token']['platform'],
          );
        }
      }
    }
  }
  curl_close($curl);
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

    <p>
      You can also import and pin these to an ipfs node running on localhost:5001, like for example with <a href="https://docs.ipfs.tech/install/ipfs-desktop/">ipfs-desktop</a>.<br />
      For this to work, you must also add "https://backup.teia.art" to the API->HTTPHeaders->Access-Control-Allow-Origin list in your ipfs config and restart the node.<br />
      Example:<br />
      <pre>
      "API": {
        "HTTPHeaders": {
          "Access-Control-Allow-Origin": [
            "https://backup.teia.art",
            "https://webui.ipfs.io",
            "http://webui.ipfs.io.ipns.localhost:8080"
          ]
        }
      },
      </pre>
      Your ipfs node is currently: <span id="nodeStatus"></span><br />
      Pinning status: <span id="pinningStatus"></span> / <?php echo count($found); ?><br />
      <a href="#" id="pinAll">Click here to start pinning</a><br />
      <span id="log"></span>
    </p>

    <?php
    $amount = count($found);
    if ($amount > 0) {
      echo "<p>Found {$amount} pinnable artifacts in {$address}.</p>";
      foreach ($found as $link) {
        echo "<a href=\"{$link['link']}\">{$link['link']}</a><br />";
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
  <script src="https://cdn.jsdelivr.net/npm/kubo-rpc-client/dist/index.min.js" defer></script>
  <script src="https://unpkg.com/multiformats/dist/index.min.js" defer></script>
  <script type="module">
    const pinAll = document.getElementById("pinAll")
    const node = KuboRpcClient.create('http://127.0.0.1:5001')
    const pinningStatus = document.getElementById("pinningStatus")
    pinningStatus.innerText = 0

    setInterval( async () => {
      const isOnline = await node.isOnline()
      const span = document.getElementById("nodeStatus")
      span.innerText = isOnline ? "RUNNING" : "DOWN";
    }, 1000)

    const log = document.getElementById("log")
    function appendLog(text) {
      const logEntry = document.createTextNode(text)
      const br = document.createElement("br")
      log.appendChild(logEntry)
      log.appendChild(br)
    }

    pinAll.addEventListener('click', async () => {
      if(await node.isOnline()) {
        // connect to teia ipfs node for peering
        await node.swarm.connect('/p2p/12D3KooWP84PmvN2ncA2vDCzoea2DGgBsEgxRreiMWpvZdpEgtrq')

        const artifacts = <?php echo json_encode(array_values($found)) . "\n"; ?>
        let count = 0
        for(const artifact of artifacts) {
          try {
            const cid = artifact.platform !== 'HEN' ? Multiformats.CID.parse(artifact.cid.toString()).toV1().toString() : artifact.cid
            for await (const { returnedCid, type } of node.pin.ls({ type: 'recursive', paths: [cid] })) {
              appendLog(`${cid} is already pinned, skipping`)
            }
          } catch {
            appendLog(`PINNING ${artifact.cid}, FETCHING ${artifact.car}`)
            try {
              const { content } = KuboRpcClient.urlSource(artifact.car, { signal: AbortSignal.timeout(5000) })
              let returnedCid
              for await (const file of node.dag.import(content)) {
                returnedCid = file.root.cid
              }

              if(Multiformats.CID.parse(returnedCid.toString()).toV1().toString() === Multiformats.CID.parse(artifact.cid.toString()).toV1().toString()) {
                appendLog(`Successfully pinned ` + artifact.car)
              } else {
                appendLog(`CID mismatch, failed to pin: ${artifact.car} EXPECTED: ${artifact.cid} GOT: ${returnedCid}`)
              }
            } catch (error) {
              appendLog(`Unable to download ${artifact.car} ${error}`)

              const cid = Multiformats.CID.parse(artifact.cid.toString())
              const pinned = await node.pin.add(cid, { signal: AbortSignal.timeout(5000) })
              appendLog(pinned)

            }
          } finally {
            count++
            pinningStatus.innerText = count
          }
        }
      } else {
        appendLog("local IPFS node is down")
      }
    })
  </script>
</body>
<!--
TODO
add better instructions on how to setup ipfs node
make ipfs node/port customizable
make ipfs gateway customizable
show ipfs node status
separate page for pinning/ipfs shizzle
add pins without waiting (don't know if this is possible)
add pins without DAG import
show pinning progress
show current pinned content
add option to remove individual/all pins
-->
</html>