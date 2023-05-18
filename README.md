## pre-install
To run this module it is crutial to set machine IP address to variable in file
```
client/config.js
```
to run this module inside SecMon you have to set mitre module address in secmon file params.php <br>
to:   https://\<host device IP\>:8085 

<b> path: </b> /var/www/html/secmon/config
```
<?php

return [
    'mitreModuleAddress' => 'http://192.168.0.150:8085'
];

```

## install
run this in root directory
```
docker compose up --build
```
after run open url <b> \<host device IP\>:8085 </b>

