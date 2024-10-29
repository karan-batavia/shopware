---
title: App permission decoupling from update process
date: 2024-11-11
area: core
tags: [app-system, permissions]
---

## Context

App permissions are tied directly to version updates.

Currently, an app requests permissions on install and then can add additional ones with every new app version update.

Also, an app can define with which Shopware version(s) it's compatible with. There could be a critical/important app update but you can't apply it automatically because there are new permissions and the shop owner has not confirmed them. You also can't update shopware to a newer version, where only an app exists which needs additional permissions without accepting it, beforehand.

## Decision

We will add a new `AppPermissionsUpdated` webhook, emitted whenever permissions are _Accepted_ by a shop owner. Apps should listen to this webhook which includes the full list of accepted privileges. These should be stored in the App backend.

Now, on the App backend you know the concrete permissions granted by the Shop owner and can make decisions based on that.

For example: 

1. I don't have permission x, therefore I don't display feature y because that requires it.
2. I don't have Order read permissions therefore I won't try to fetch new Orders

When an update updates requires new permissions, they will be stored separately from the accepted permissions as _Requested_ permissions.

When an app is installed/updated on the CLI, the permissions will be auto accepted, because the command prompts for agreement. Unless that is, that the `--force` option is used. Then the permissions will no longer be prompted for, and will be added as _Requested_ permissions.

We will provide a new API to fetch and accept the permissions. These API's should be consumed by the Administration only and not the app.

### Fetch current permission requests

`GET /api/app-system/permissions/requested`

It will return a JSON response like:

```
{
    "requestedPermissions": {
        "01931ac853f372b4abe3d652d013e733": {
            "admin_user": [
                {
                    "extensions": [],
                    "entity": "user",
                    "operation": "read"
                }
            ],
            "customer": [
                {
                    "extensions": [],
                    "entity": "customer",
                    "operation": "read"
                },
                {
                    "extensions": [],
                    "entity": "customer_address",
                    "operation": "create"
                },
                ...
            ],
            ...,
            "additional_privileges": [
                {
                    "extensions": [],
                    "entity": "additional_privileges",
                    "operation": "api_service_toggle"
                }
            ],
        }
    }
}
```

Note: the requests are keyed by the app's ID.

### Accept permission requests

Permissions are accepted on a per app basis, and it's possible to accept a subset of what has been requested.

`POST /api/app-system/{appId}/permissions/accept`

It requires a payload with a list of the accepted permissions in the format: `entity:privilege`, eg `product:read`.

```
["user:read"]
```

### Services

For services, we will introduce a few new routes which build on top of the work involved for decoupling app permissions from the install/update process.

Services work a bit different to Apps, they are accept once and all. Eg, you consent, or you do not. You cannot accept one services permission requests or a subset of the permissions. You also do _NOT_ get prompted for new permissions required in service updates. You accept once, and for all. Until you revoke all.

`GET /api/services/consent`

```
{
    "status": "pending",
    "dataAgreementUrl": "http://127.0.0.1:8000/services-data.html",
    "requestedPermissions": {
        "customer": [
            {
                "extensions": [],
                "entity": "customer",
                "operation": "read"
            },
            ...,
        ],
        "order": [
            {
                "extensions": [],
                "entity": "order",
                "operation": "create"
            },
        ]
    },
}
```

The response includes the current statuses "pending", "accepted" or "declined". A list of requested permissions (a merged list of the currently required permissions for all the installed services).

Finally, it includes a URL to a document which explains the data sharing policy of services and how the whole process works (content tbd) - but the key point is that we can update it without a platform update.

`POST /api/services/accept-consent`

This endpoint has no request body. It accepts all permissions (for current services and new and updated services). A simple flag is stored in the configuration table to allow auto accepting permissions in the future.

`POST /api/services/revoke-consent`

This endpoint also has no request body. It revokes all permissions for services. It also updates the config flag so as not to auto accept future permissions.

## Consequences

This requires a fundamental shift in the way App backends are developed and function. They, themselves, are responsible for checking if the owner has accepted a permission before performing a certain action.

Note: Platform will still validate on it's side whether an action can be performed based on the currently accepted permissions.

## Out of scope

This document does not cover how the Shopware Admin uses this data/API and what the process and UI is to actually accept permissions.
