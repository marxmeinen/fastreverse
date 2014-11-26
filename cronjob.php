<?php

/*
Database schema:

+----------------------------------------------------+
| Table: devices                                     |
+----------------------------------------------------+
| Pk | Name        | IP          | Status | PingPort |
+----------------------------------------------------+
| 1  | Marx iPhone | 192.168.1.2 | Active | 80       |
+----------------------------------------------------+
Status:
1 = Active device 
2 = Inactive device

+----------------------------------------------------+
| Table: logs                                        |
+----------------------------------------------------+
| Pk | Device_pk | Datetime            | Status      |
+----------------------------------------------------+
| 1  | 1         | 22-11-2014 10:10:30 | 1           | 
+----------------------------------------------------+
Status:
1 = online client
2 = offline client

+----------------------------------------------------+
| Table: actions                                     |
+----------------------------------------------------+
| Pk | Device_pk | Label          | Status | URL     |
+----------------------------------------------------+
| 1  | 1         | Buitenlamp aan | 1      | http:// |
+----------------------------------------------------+
Status:
1 = Active
2 = Inactive
*/

// This file is the cronjob file for every 5 seconds
// First, load all the IP's from the database and loop 

// Scan the IP's
$host = '192.168.0.114'; 
$output = shell_exec('ping -c1 '.$host.'');
echo "<pre>$output</pre>";
echo gethostbyname($host);

?>