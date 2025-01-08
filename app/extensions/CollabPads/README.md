# CollabPads


## Installation

Execute

    composer require mediawiki/collabpads dev-main

within MediaWiki root or add `mediawiki/collabpads` to the `composer.json` file of your project

## Activation

Add

    wfLoadExtension( 'CollabPads' );

to your `LocalSettings.php` or the appropriate `settings.d/` file.

## Settings and server configuration

`CollabPads/backend/` contains two config files

* `config.docker.php`
```
config => default_value // comment

...
'server-id' => 'mediawiki-collabpads-backend', // Server name
'ping-interval' => 25000,		// Interval between keep-alive requests after a connection is made
'ping-timeout' => 5000,			// Time to wait for an is-alive response from the server
'request-ip' => '0.0.0.0',		// The address to receive sockets on (0.0.0.0 means receive connections from any)
...
```

* `sample.env`
```
config=default_value // comment

COLLABPADS_BACKEND_PORT=8081					// CollabPad server port to listen on
COLLABPADS_BACKEND_WIKI_BASEURL=http://host.docker.internal/w	// URL to your wiki's REST API endpoint
COLLABPADS_BACKEND_MONGO_DB_HOST=database		// MongoDB host for database connection
COLLABPADS_BACKEND_MONGO_DB_PORT=27017			// MongoDB port for database connection
COLLABPADS_BACKEND_MONGO_DB_NAME=collabpads		// MongoDB name for database connection
COLLABPADS_BACKEND_MONGO_DB_USER=				// MongoDB user for database login
COLLABPADS_BACKEND_MONGO_DB_PASSWORD=			// MongoDB password for database login
COLLABPADS_BACKEND_LOGLEVEL=warn				// CollabPad server logging level error/warn/info/notice/debug
COLLABPADS_BACKEND_HTTP_CLIENT_OPTIONS={}		// HTTP client options
```

Configure `COLLABPADS_BACKEND_WIKI_BASEURL` to the URL of your wiki's REST API endpoint.

Rename `sample.env` to `.env`

	mv sample.env .env

Configure `CollabPadsBackendServiceURL` and add

    $GLOBALS['wgCollabPadsBackendServiceURL'] = 'http://example-url:8081/collabpad';

to your `LocalSettings.php` or the appropriate `settings.d/` file.

Note: Use the same port as `COLLABPADS_BACKEND_PORT`

## Start the server

`CollabPads/backend/`

Build the docker image

    docker build -t hallowelt/collabpads-backend:1.0 .

Start the server

    docker compose --env-file .env up --force-recreate

## Usage

Start a CollabPad session
- Open the page you want to edit in collaborative mode
- (DiscoverySkin) Select 'Edit collaboratively' from the pencil icon drop down menu

## Error-handling and security

- After running `docker compose --env-file .env up --force-recreate`, server logs will be written in the respective container.
- If you encounter any problems, please contact HS, AK or RV.

