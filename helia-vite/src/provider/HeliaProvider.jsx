/* eslint-disable no-console */

import { unixfs } from '@helia/unixfs'
import { car } from '@helia/car'
import { createHelia } from 'helia'
import PropTypes from 'prop-types'
import {
  React,
  useEffect,
  useState,
  useCallback,
  createContext
} from 'react'

import { IDBBlockstore } from 'blockstore-idb'
import { IDBDatastore } from 'datastore-idb'

export const HeliaContext = createContext({
  helia: null,
  fs: null,
  carimport: null,
  error: false,
  starting: true
})

export const HeliaProvider = ({ children }) => {
  const [helia, setHelia] = useState(null)
  const [fs, setFs] = useState(null)
  const [carimport, setCarimport] = useState(null)
  const [starting, setStarting] = useState(true)
  const [error, setError] = useState(null)

  const startHelia = useCallback(async () => {
    if (helia) {
      console.info('helia already started')
    } else if (window.helia) {
      console.info('found a windowed instance of helia, populating ...')
      setHelia(window.helia)
      setFs(unixfs(helia))
      setCarimport(car(helia))
      setStarting(false)
    } else {
      try {
        console.info('Starting Helia')
        const blockstore = new IDBBlockstore('blockstore')
        await blockstore.open()
        const datastore = new IDBDatastore('datastore')
        await datastore.open()
        const helia = await createHelia({
          blockstore, 
          datastore,
        })
        setHelia(helia)
        setFs(unixfs(helia))
        setCarimport(car(helia))
        setStarting(false)
      } catch (e) {
        console.error(e)
        setError(true)
      }
    }
  }, [])

  useEffect(() => {
    startHelia()
  }, [])

  return (
    <HeliaContext.Provider
      value={{
        helia,
        fs,
        carimport,
        error,
        starting
      }}
    >{children}</HeliaContext.Provider>
  )
}

HeliaProvider.propTypes = {
  children: PropTypes.any
}
