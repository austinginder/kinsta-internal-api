### PHP script for talking to Kinsta's internal API

While Kinsta is working on an official API, it currently doesn't support retrieving basic site information like username, SSH addresses or passwords. This script is an extraction from [CaptainCore](https://captaincore.io), my toolkit for WordPress maintenance.

Start by cloning the repo locally and switch to that directory.

```
git clone https://github.com/austinginder/kinsta-internal-api.git
cd kinsta-internal-api/
```

Next copy `credentials-sample.json` to `credentials.json` and replace with your Kinsta Company ID, Kinsta password and Kinsta token. 

To retrieve your current Kinsta token, sign into [https://my.kinsta.com](https://my.kinsta.com) and open Chrome DevTools. Type into the console `localStorage.getItem("com.kinsta.shared.loginToken")` then copy the 180 length string.


Start the script by running:

```
php kinsta-internal-api.php
```

The script will generate 2 files `data.json` and `environments.json`. The `data.json` stores the raw responses from Kinsta whereas the `environments.json` is a cleaned up JSON output with all of the environments included the full details. If more info is needed in the final output, that is possible by digging through the raw output and tweaking the processed output.

Hope this is helpful.