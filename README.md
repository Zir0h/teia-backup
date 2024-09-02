POC to quickly backup a collection using https://www.downthemall.net/

For pinning:
Download https://docs.ipfs.tech/install/ipfs-desktop/
Go to the config section and update the "Access-Control-Allow-Origin" section like this:
```
			"Access-Control-Allow-Origin": [
				"https://backup.teia.art",
				"https://webui.ipfs.io",
				"http://webui.ipfs.io.ipns.localhost:8080"
			]
```
Restart the ipfs-desktop application
