/* global Helia, HeliaUnixfs */

const statusValueEl = document.getElementById('statusValue')
const discoveredPeerCountEl = document.getElementById('discoveredPeerCount')
const connectedPeerCountEl = document.getElementById('connectedPeerCount')
const connectedPeersListEl = document.getElementById('connectedPeersList')
const logEl = document.getElementById('runningLog')
const nodeIdEl = document.getElementById('nodeId')
const importButton = document.getElementById('importButton')
const addressField = document.getElementById('addressField')

document.addEventListener('DOMContentLoaded', async () => {
  const helia = window.helia = await instantiateHeliaNode()
  window.heliaFs = await HeliaUnixfs.unixfs(helia)

  helia.libp2p.addEventListener('peer:discovery', (evt) => {
    window.discoveredPeers.set(evt.detail.id.toString(), evt.detail)
    addToLog(`Discovered peer ${evt.detail.id.toString()}`)
  })

  helia.libp2p.addEventListener('peer:connect', (evt) => {
    addToLog(`Connected to ${evt.detail.toString()}`)
  })
  helia.libp2p.addEventListener('peer:disconnect', (evt) => {
    addToLog(`Disconnected from ${evt.detail.toString()}`)
  })

  setInterval(() => {
    statusValueEl.innerHTML = helia.libp2p.status === 'started' ? 'Online' : 'Offline'
    updateConnectedPeers()
    updateDiscoveredPeers()
  }, 500)

  importButton.addEventListener("click", importFiles);

  const id = await helia.libp2p.peerId.toString()

  nodeIdEl.innerHTML = id
  /*
    const pins = await helia.pins.ls()
    for await (const pin of pins) {
      console.log(await helia.blockstore.has(Multiformats.CID.parse(pin.cid)))
    }
  
    console.log(pins.length)
  */
  /**
   * You can write more code here to use it.
   *
   * https://github.com/ipfs/helia
   * - helia.start
   * - helia.stop
   *
   * https://github.com/ipfs/helia-unixfs
   * - heliaFs.addBytes
   * - heliaFs.addFile
   * - heliaFs.ls
   * - heliaFs.cat
   */
})

function ms2TimeString(a) {
  const k = a % 1e3
  const s = a / 1e3 % 60 | 0
  const m = a / 6e4 % 60 | 0
  const h = a / 36e5 % 24 | 0

  return (h ? (h < 10 ? '0' + h : h) + ':' : '00:') +
    (m < 10 ? 0 : '') + m + ':' +
    (s < 10 ? 0 : '') + s + ':' +
    (k < 100 ? k < 10 ? '00' : 0 : '') + k
}

const getLogLineEl = (msg) => {
  const logLine = document.createElement('span')
  logLine.innerHTML = `${ms2TimeString(performance.now())} - ${msg}`

  return logLine
}
const addToLog = (msg) => {
  logEl.appendChild(getLogLineEl(msg))
}

let heliaInstance = null
const instantiateHeliaNode = async () => {
  if (heliaInstance != null) {
    return heliaInstance
  }

  heliaInstance = await Helia.createHelia()
  console.log(heliaInstance)
  addToLog('Created Helia instance')

  return heliaInstance
}

window.discoveredPeers = new Map()

const updateConnectedPeers = () => {
  const peers = window.helia.libp2p.getPeers()
  connectedPeerCountEl.innerHTML = peers.length
  connectedPeersListEl.innerHTML = ''
  for (const peer of peers) {
    const peerEl = document.createElement('li')
    peerEl.innerText = peer.toString()
    connectedPeersListEl.appendChild(peerEl)
  }
}

const updateDiscoveredPeers = () => {
  discoveredPeerCountEl.innerHTML = window.discoveredPeers.size
}

const getFile = async (file) => {
  const res = await fetch(file)
  const data = await res.blob()
  const blob = new Uint8Array(await data.arrayBuffer());
  return blob
}

const importCar = async (CAR) => {
  const reader = await IpldCar.CarReader.fromBytes(CAR)
  const c = HeliaCar.car(heliaInstance)
  await c.import(reader)
}

const getFilesToImport = async (address) => {
  const carFiles = await (await fetch(`index.php?address=${address}&asJSON=on&useCAR=on`)).json()
  return carFiles
}

const importFiles = async () => {
  const address = addressField.value
  const toImport = await getFilesToImport(address)
  await Promise.all(toImport.map(async (link) => {
    const cid = Multiformats.CID.parse(link.cid)
    const isPinned = await heliaInstance.pins.isPinned(cid)
    if (!isPinned) {
      addToLog(`${link.cid} is not pinned, trying again`)
      addToLog(`Downloading link: ${link.url}`)
      const CAR = await getFile(link.url)
      await importCar(CAR)
      await heliaInstance.pins.add(cid)
      if (!await heliaInstance.pins.isPinned(cid)) {
        addToLog(`${link.cid} is not yet pinned, giving up`)
      } else {
        addToLog(`${link.cid} IS PINNED!!!!!`)
      }
    } else {
      addToLog(`${link.cid} IS PINNED!!!!!`)
    }
  }))
}