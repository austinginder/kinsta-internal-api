<?php

require_once dirname(__FILE__) . "/vendor/autoload.php";
WpOrg\Requests\Autoload::register();

$endpoint          = "https://my.kinsta.com/gateway";
$data_file         = dirname(__FILE__) . "/data.json";
$environments_file = dirname(__FILE__) . "/environments.json";
$sites             = [];
$credentials       = json_decode ( file_get_contents( "credentials.json" ) );
$options           = [
	'timeout' => 60,
];

if ( is_file( $data_file ) ) {
    $sites      = json_decode( file_get_contents( "data.json" ) );
    echo "Previously extracted ". count( $sites ) . " sites from MyKinsta\n";
}

echo "Requesting password token from MyKinsta for password fetching\n";
$response = WpOrg\Requests\Requests::post( $endpoint, [ 
    'Content-Type' => 'application/json',
    'X-Token'      => $credentials->token
],
json_encode( [
    "variables" => [ 
        "password" => $credentials->password
    ],
    "operationName" => "PasswordToken",
    "query"         => 'query PasswordToken($password: String!) {
        passwordToken(password: $password)
      }'
] ), $options
);

$response->body = json_decode( $response->body );
$password_token = $response->body->data->passwordToken;

echo "Querying site list from MyKinsta\n";


$response = WpOrg\Requests\Requests::post( $endpoint, [ 
        'Content-Type' => 'application/json',
        'X-Token'      => $credentials->token
    ],
    json_encode( [
        "variables" => [ 
            "idCompany" => $credentials->company,
            "isSuperAdmin" => false
        ],
        "operationName" => "SiteListFull",
        "query"         => 'query SiteListFull($idCompany: String!, $isSuperAdmin: Boolean!) {
            company(id: $idCompany) {
              name
              id
              sites {
                id
                ...siteData
                siteLabels {
                  id
                  name
                  __typename
                }
                siteLabels_liveKeys
                __typename
              }
              incomingSites {
                id
                ...siteData
                __typename
              }
              sites_liveKeys
              incomingSites_liveKeys
              __typename
            }
          }
          
          fragment siteData on Site {
            id
            displayName
            name
            idCompany
            status
            keycdnZone {
              id
              enabled
              __typename
            }
            thisMonthUsage {
              bandwidth
              uniqueip
              __typename
            }
            environment(name: "live") {
              id
              status
              isCfCdnOn
              kinstaDomains @include(if: $isSuperAdmin) {
                id
                domains
                idSiteDomain
                __typename
              }
              domains {
                id
                name
                __typename
              }
              activeContainer {
                id
                forceHttps
                phpEngine
                diskUsage(cacheControl: ASYNC) {
                  dbSize
                  publicFolderSize
                  __typename
                }
                lxdServer {
                  id
                  intHostname
                  zone {
                    id
                    name
                    region
                    __typename
                  }
                  __typename
                }
                diskUsage_liveKeys(cacheControl: ASYNC)
                __typename
              }
              __typename
            }
            environments {
              id
              status
              name
              displayName
              isPremium
              domains @include(if: $isSuperAdmin) {
                id
                name
                __typename
              }
              containers @include(if: $isSuperAdmin) {
                id
                name
                __typename
              }
              activeContainer @include(if: $isSuperAdmin) {
                id
                lxdServer {
                  id
                  intHostname
                  __typename
                }
                __typename
              }
              __typename
            }
            hasStaging: environment(name: "staging") {
              id
              __typename
            }
            hasPendingTransfer
            hasPendingTransfer_liveKeys
            __typename
          }'
    ] ), $options
);

$response->body = json_decode( $response->body );
$site_ids       = array_column( $sites, "id" );
$count          = 1;
$total_sites    = count( $response->body->data->company->sites );
echo "Found $total_sites sites in MyKinsta\n";

foreach( $response->body->data->company->sites as $site ) {
    if ( in_array( $site->id, $site_ids ) ) {
        echo "Already added $site->displayName ($count/$total_sites)\n";
        # Skip existing sites, only query site details for new sites.
        $count++;
        continue;
    }
    foreach ( $site->environments as $environment ) {
        echo "Fetching envirvonment $environment->displayName for $site->displayName ($count/$total_sites)\n";
        $response_environment = WpOrg\Requests\Requests::post( $endpoint, [ 
            'Content-Type' => 'application/json',
            'X-Token'      => $credentials->token
        ],
        json_encode( [
            "variables" => [ 
                "idSite"        => $site->id,
                "idEnvironment" => $environment->id
            ],
            "operationName" => "SiteDetails",
            "query"         => 'query SiteDetails($idSite: String!, $idEnvironment: String) {
                site(id: $idSite) {
                  id
                  name
                  displayName
                  dbName
                  path
                  usr
                  siteLabels {
                    id
                    name
                    __typename
                  }
                  company {
                    id
                    name
                    __typename
                  }
                  environment(id: $idEnvironment) {
                    id
                    isPremium
                    cloudflareIP
                    customHostnames {
                      id
                      status
                      __typename
                    }
                    phpWorkerLimit
                    mysqlEditorDomain {
                      id
                      name
                      __typename
                    }
                    activeContainer {
                      id
                      lxdSshPort
                      loadBalancer {
                        id
                        extIP
                        __typename
                      }
                      lxdServer {
                        id
                        extIP
                        intHostname
                        zone {
                          id
                          name
                          __typename
                        }
                        __typename
                      }
                      __typename
                    }
                    customHostnames_liveKeys
                    activeContainer_liveKeys
                    __typename
                  }
                  hasPendingTransfer
                  hasFreeEnvSlot
                  siteLabels_liveKeys
                  environment_liveKeys(id: $idEnvironment)
                  hasPendingTransfer_liveKeys
                  hasFreeEnvSlot_liveKeys
                  __typename
                }
              }'
        ] ), $options
        );
        $response_environment->body = json_decode( $response_environment->body );
        $environment->details = $response_environment->body->data->site;
        $response_environment = WpOrg\Requests\Requests::post( $endpoint, [ 
            'Content-Type' => 'application/json',
            'X-Token'      => $credentials->token
        ],
        json_encode( [
            "variables" => [ 
                "idSite"        => $site->id,
                "idEnv"         => $environment->id,
                "passwordToken" => $password_token
            ],
            "operationName" => "SftpPassword",
            "query"         => 'query SftpPassword($idSite: String!, $idEnv: String!, $passwordToken: String) {
                site(id: $idSite) {
                  id
                  environment(id: $idEnv) {
                    id
                    sftpPassword(passwordToken: $passwordToken)
                    __typename
                  }
                  __typename
                }
              }'
        ] ), $options
        );
        $response_environment->body = json_decode( $response_environment->body );
        $environment->sftp_password = $response_environment->body->data->site->environment->sftpPassword;
    }

    $sites[] = $site;
    $count++;
}

file_put_contents( $data_file, json_encode( $sites, JSON_PRETTY_PRINT ) );

$environments_processed = [];
foreach ( $sites as $site ) {
    foreach( $site->environments as $environment ) {
        $environments_processed[] = [
            "name"              => $site->displayName,
            "environment"       => $environment->displayName,
            "address"           => $environment->details->environment->activeContainer->loadBalancer->extIP,
            "username"          => $environment->details->usr,
            "password"          => $environment->sftp_password,
            "port"              => $environment->details->environment->activeContainer->lxdSshPort,
            "home_directory"    => "/www/{$environment->details->path}/public",
            "database_username" => $environment->details->dbName,
            "zone"              => $environment->details->environment->activeContainer->lxdServer->zone->name,
            "mysqlEditorDomain" => $environment->details->environment->mysqlEditorDomain->name,
        ];
    }
}

file_put_contents( $environments_file, json_encode( $environments_processed, JSON_PRETTY_PRINT ) );
?>