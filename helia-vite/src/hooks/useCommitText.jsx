/* eslint-disable no-console */

import { useState, useCallback } from 'react'
import { useHelia } from '@/hooks/useHelia'
import { CID } from 'multiformats'
import { CarReader } from '@ipld/car'

const encoder = new TextEncoder()
const decoder = new TextDecoder()

export const useCommitText = () => {
  const { helia, fs, carimport, error, starting } = useHelia()
  const [cid, setCid] = useState(null)
  const [cidString, setCidString] = useState('')
  const [committedText, setCommittedText] = useState('')

  const commitText = useCallback(async (text) => {
    if (!error && !starting) {
      try {
        const cid = await fs.addBytes(
          encoder.encode(text),
          helia.blockstore
        )
        setCid(cid)
        setCidString(cid.toString())
        console.log('Added file:', cid.toString())
      } catch (e) {
        console.error(e)
      }
    } else {
      console.log('please wait for helia to start')
    }
  }, [error, starting, helia, fs])

  const fetchCommittedText = useCallback(async () => {
    let text = ''
    if (!error && !starting) {
      try {
        for await (const chunk of fs.cat(cid)) {
          text += decoder.decode(chunk, {
            stream: true
          })
        }
        setCommittedText(text)
      } catch (e) {
        console.error(e)
      }
    } else {
      console.log('please wait for helia to start')
    }
  }, [error, starting, cid, helia, fs])
  // If one forgets to add helia in the dependency array in commitText, additions to the blockstore will not be picked up by react, leading to operations on fs to hang indefinitely in the generator <suspend> state. As such it would be good practice to ensure to include helia inside the dependency array of all hooks to tell react that the useCallback needs the most up to date helia state

  const addToLocalNode = useCallback(async (text) => {
    if (!error && !starting) {

      const getFile = async (file) => {
        const res = await fetch(file)
        const data = await res.blob()
        const blob = new Uint8Array(await data.arrayBuffer());
        return blob
      }
    
      const importCar = async (CAR, helia) => {
        const reader = await CarReader.fromBytes(CAR)
        const c = carimport(helia)
        await c.import(reader)
      }

      try {

        const address = text

        const filesToImport = await (await fetch(`http://localhost:8080/index.php?address=${address}&asJSON=on&useCAR=on`)).json()

        await Promise.all(filesToImport.map(async (link) => {
          const cid = CID.parse(link.cid)
          const isPinned = await helia.pins.isPinned(cid)
          if (!isPinned) {
            console.log(`Downloading link: ${link.url}`)
            const CAR = await getFile(link.url)
            await importCar(CAR, helia)

            await helia.pins.add(cid)
            if (!await helia.pins.isPinned(cid)) {
              console.log(`${link.cid} is not yet pinned, giving up`)
            } else {
              console.log(`${link.cid} IS PINNED!!!!!`)
            }
          } else {
            console.log(`${link.cid} IS PINNED!!!!!`)
          }
        }))
      } catch (e) {
        console.error(e)
      }
    } else {
      console.log('please wait for helia to start')
    }
  }, [error, starting, helia, fs, carimport])

  return { cidString, committedText, commitText, fetchCommittedText, addToLocalNode }
}
