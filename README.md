# libmodstamp

This is a simple PHP library implementing a server and client for the modstamp protocol.

The modstamp protocol is used for distributing change events over a non-reliable network where all traffic is originating from client to server which means, that clients may
even lay behind NAT and still be able to receive change events from the server.

## The modstamp protocol

### Basic usage scenario

The modstamp protocol may be used in cases when you need to notify some agents (modstamp clients) whenever a change happens in some some dataset.

The basic scenario of using the modstamp protocol is as follows:

* Whenever a change happens in the monitored dataset, a modstamp value needs to be assigned to that change. A modstamp value may be any string.
  The only important point about the modstamp value is that same modstamp value means no change happen. Multiple strategies of assigning
  a modstamp value are possible:
    - use an incremental number. Whenever the dataset changes, increment the modstamp value by one.
    - modstamp value may be a timestamp of the change
    - modstamp value may be just a random string always regenerated when a change happens
* The modstamp value is assigned to a modstamp ID, which uniqly identifies the monitored dataset on the modstamp server.
* The code monitoring the dataset sends a modstamp change request to the modstamp server.
* Modstamp server stores the modstamp value in its own database.
* Modstamp server distributes the modstamp value to all listening clients over the modstamp protocol as soon as possible. If the network connection
  is reliable, changes of the modstamp value are distributed to the listening clients immediately. If there is some network outage, the change
  will be distributed as soon as possible.
* Client receives the change and may trigger any operation based on the change. For example resynchronization from the server.


Modstamp protocol is UDP based. Therefore it is very lightweight. The protocol is designed in a way so that all traffic always originates from the client.

### Responsibilities

#### Modstamp server

Modstamp server is responsible for keeping the values of all modstamps and for distributing modstamp changes to clients.

#### Modstamp client

Modstamp client may be used for two purposes:

1. listening for modstamp changes
2. pushing modstamp changes to the server

Modstamp client is responsible for keeping the connection alive by periodically querying the modstamp server when listening for modstamp changes.

## libmodstamp implementation

`libmodstamp` implements both - the server code and also the client code. But it does not provide any interface for running the server or client as standalone applications. However the code for implementing both is quite easy and straightforward.

### requirements

The modstamp client does require only `php >= 8.0`. The modstamp server also requires `php-redis` and `php-sqlite3` extensions and a redis instance to manage chached data.

### server code

```php
use Sterzik\ModStamp\Server;
use Sterzik\ModStamp\ServerConfig;

$serverConfig = new ServerConfig();
$serverConfig
    // set the redis host to communicate:
    ->setRedisHost("redis") 
    // the modstamp database file is sqlite3 based:
    ->setModstampDatabaseFile("/path/to/modstamp/database/file.db")
;
$server = new Server($serverConfig);
$server->serve();
```

### client

#### common code for creating any client

```php
use Sterzik\ModStamp\Client;
use Sterzik\ModStamp\ClientConfig;

$clientConfig = new ClientConfig("localhost");
$client = new Client($clientConfig);
```

#### listening client

The following code is listening to modstamp with id `test`. The value `null` just means the initial modstamp. If set to null, it will not trigger the initial change.
If set to any string modstamp value, the initial change will be triggered if the fetched modstamp value will differ from the one stored in the array.

```php
// create $client as in the previous example
$client->listenForChanges(["test" => null], function ($modstamp, $modstampValue) {
    echo "Modstamp '$modstamp' changed its value to '$modstampValue'\n";
});
```

#### client pushing changes

The following example will set the value of modstamp id `test` to `abc`:

```php
// create $client as in the previous example
$confirmedModstamps = $client->sendModstamps(["test" => "abc"]);

// $confirmedModstamps contain an array of modstamp id-value pairs
// which were confirmed by the server.
````

#### client set

A client set is intended to send modstamps into multiple servers:

```php
use Sterzik\ModStamp\ClientSet;

$client1 = create_client(1);
$client2 = create_client(2);

$clientSet = new ClientSet([$client1, $client2]);

$confirmedModstamps = $clientSet-.sendModstamps(["test" => "abc"]);

// $confirmedModstamps contain an array of modstamp id-value pairs
// confirmed by ALL inidividual clients
```
