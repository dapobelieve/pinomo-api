JSON

{  
  "openapi": "3.0.0",  
  "info": {  
    "title": "Core Banking Platform API",  
    "version": "1.0.0",  
    "description": "API-first Core Banking Platform for managing customers, accounts, transactions, ledgers, products, charges, and reporting.\\nDesigned for scalability, event-driven architecture, and configurable workflows.\\nThis specification outlines the RESTful endpoints and data models."  
  },  
  "servers": \[  
    {  
      "url": "https://api.yourbank.com/v1",  
      "description": "Production Server"  
    },  
    {  
      "url": "https://api.sandbox.yourbank.com/v1",  
      "description": "Sandbox Server"  
    }  
  \],  
  "security": \[  
    {  
      "bearerAuth": \[\]  
    }  
  \],  
  "tags": \[  
    {  
      "name": "Clients",  
      "description": "Client and customer management operations."  
    },  
    {  
      "name": "Accounts",  
      "description": "Financial account management (savings, current, loan)."  
    },  
    {  
      "name": "Transactions",  
      "description": "Funds movement and posting operations."  
    },  
    {  
      "name": "KYC",  
      "description": "Know Your Customer (KYC) management and configurations."  
    },  
    {  
      "name": "Products",  
      "description": "Financial product definitions."  
    },  
    {  
      "name": "Charges",  
      "description": "Fee and charge management."  
    },  
    {  
      "name": "Ledgers",  
      "description": "General Ledger and accounting operations."  
    },  
    {  
      "name": "Workflows",  
      "description": "Business process workflow management."  
    },  
    {  
      "name": "Audit & Reporting",  
      "description": "System audit trails and data reporting."  
    }  
  \],  
  "paths": {  
    "/clients": {  
      "get": {  
        "tags": \[  
          "Clients"  
        \],  
        "summary": "List all clients",  
        "description": "Retrieve a paginated list of all individual and organizational clients.",  
        "operationId": "listClients",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "client.read"  
            \]  
          }  
        \],  
        "parameters": \[  
          {  
            "in": "query",  
            "name": "page",  
            "schema": {  
              "type": "integer",  
              "minimum": 1  
            },  
            "description": "Page number for pagination.",  
            "example": 1  
          },  
          {  
            "in": "query",  
            "name": "per\_page",  
            "schema": {  
              "type": "integer",  
              "minimum": 1,  
              "maximum": 100  
            },  
            "description": "Number of items per page.",  
            "example": 20  
          },  
          {  
            "in": "query",  
            "name": "client\_type",  
            "schema": {  
              "type": "string",  
              "enum": \[  
                "individual",  
                "organization"  
              \]  
            },  
            "description": "Filter clients by type.",  
            "example": "individual"  
          },  
          {  
            "in": "query",  
            "name": "status",  
            "schema": {  
              "type": "string",  
              "enum": \[  
                "pending\_kyc",  
                "active",  
                "inactive",  
                "suspended",  
                "closed"  
              \]  
            },  
            "description": "Filter clients by status.",  
            "example": "active"  
          }  
        \],  
        "responses": {  
          "200": {  
            "description": "A paginated list of clients.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "type": "object",  
                  "properties": {  
                    "data": {  
                      "type": "array",  
                      "items": {  
                        "$ref": "\#/components/schemas/Client"  
                      }  
                    },  
                    "meta": {  
                      "$ref": "\#/components/schemas/PaginationMeta"  
                    }  
                  }  
                }  
              }  
            }  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      },  
      "post": {  
        "tags": \[  
          "Clients"  
        \],  
        "summary": "Create a new client",  
        "description": "Create a new individual or organizational client.",  
        "operationId": "createClient",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "client.create"  
            \]  
          }  
        \],  
        "requestBody": {  
          "required": true,  
          "content": {  
            "application/json": {  
              "schema": {  
                "$ref": "\#/components/schemas/ClientCreateRequest"  
              }  
            }  
          }  
        },  
        "responses": {  
          "201": {  
            "description": "Client created successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "type": "object",  
                  "properties": {  
                    "client\_id": {  
                      "type": "string",  
                      "format": "uuid",  
                      "description": "Unique ID of the created client."  
                    },  
                    "status": {  
                      "type": "string",  
                      "enum": \[  
                        "pending\_kyc",  
                        "active",  
                        "inactive",  
                        "suspended",  
                        "closed"  
                      \],  
                      "description": "Initial status of the client."  
                    }  
                  }  
                }  
              }  
            }  
          },  
          "400": {  
            "$ref": "\#/components/responses/BadRequestError"  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "422": {  
            "$ref": "\#/components/responses/ValidationError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/clients/{clientId}": {  
      "get": {  
        "tags": \[  
          "Clients"  
        \],  
        "summary": "Get client details",  
        "description": "Retrieve details for a specific client by ID.",  
        "operationId": "getClientById",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "client.read"  
            \]  
          }  
        \],  
        "parameters": \[  
          {  
            "in": "path",  
            "name": "clientId",  
            "schema": {  
              "type": "string",  
              "format": "uuid"  
            },  
            "required": true,  
            "description": "Unique ID of the client."  
          }  
        \],  
        "responses": {  
          "200": {  
            "description": "Client details retrieved successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "$ref": "\#/components/schemas/Client"  
                }  
              }  
            }  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "404": {  
            "$ref": "\#/components/responses/NotFoundError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      },  
      "put": {  
        "tags": \[  
          "Clients"  
        \],  
        "summary": "Update client details",  
        "description": "Update details for a specific client by ID.",  
        "operationId": "updateClient",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "client.update"  
            \]  
          }  
        \],  
        "parameters": \[  
          {  
            "in": "path",  
            "name": "clientId",  
            "schema": {  
              "type": "string",  
              "format": "uuid"  
            },  
            "required": true,  
            "description": "Unique ID of the client."  
          }  
        \],  
        "requestBody": {  
          "required": true,  
          "content": {  
            "application/json": {  
              "schema": {  
                "$ref": "\#/components/schemas/ClientUpdateRequest"  
              }  
            }  
          }  
        },  
        "responses": {  
          "200": {  
            "description": "Client updated successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "$ref": "\#/components/schemas/Client"  
                }  
              }  
            }  
          },  
          "400": {  
            "$ref": "\#/components/responses/BadRequestError"  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "404": {  
            "$ref": "\#/components/responses/NotFoundError"  
          },  
          "422": {  
            "$ref": "\#/components/responses/ValidationError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/clients/{clientId}/kyc-status-history": {  
      "get": {  
        "tags": \[  
          "Clients",  
          "KYC"  
        \],  
        "summary": "Get KYC status history for a client",  
        "description": "Retrieve the historical changes of a client's KYC status.",  
        "operationId": "getClientKycStatusHistory",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "client.read.kyc\_history"  
            \]  
          }  
        \],  
        "parameters": \[  
          {  
            "in": "path",  
            "name": "clientId",  
            "schema": {  
              "type": "string",  
              "format": "uuid"  
            },  
            "required": true,  
            "description": "Unique ID of the client."  
          }  
        \],  
        "responses": {  
          "200": {  
            "description": "KYC status history retrieved successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "type": "object",  
                  "properties": {  
                    "data": {  
                      "type": "array",  
                      "items": {  
                        "$ref": "\#/components/schemas/ClientKycStatusHistory"  
                      }  
                    }  
                  }  
                }  
              }  
            }  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "404": {  
            "$ref": "\#/components/responses/NotFoundError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/accounts": {  
      "post": {  
        "tags": \[  
          "Accounts"  
        \],  
        "summary": "Create a new account",  
        "description": "Create a new financial account for a client, linked to a product and KYC level.",  
        "operationId": "createAccount",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "account.create"  
            \]  
          }  
        \],  
        "requestBody": {  
          "required": true,  
          "content": {  
            "application/json": {  
              "schema": {  
                "$ref": "\#/components/schemas/AccountCreateRequest"  
              }  
            }  
          }  
        },  
        "responses": {  
          "201": {  
            "description": "Account created successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "type": "object",  
                  "properties": {  
                    "account\_id": {  
                      "type": "string",  
                      "format": "uuid"  
                    },  
                    "account\_number": {  
                      "type": "string"  
                    },  
                    "status": {  
                      "type": "string"  
                    }  
                  }  
                }  
              }  
            }  
          },  
          "400": {  
            "$ref": "\#/components/responses/BadRequestError"  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "422": {  
            "$ref": "\#/components/responses/ValidationError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/accounts/{accountId}": {  
      "get": {  
        "tags": \[  
          "Accounts"  
        \],  
        "summary": "Get account details",  
        "description": "Retrieve details for a specific financial account, including its current balances.",  
        "operationId": "getAccountById",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "account.read"  
            \]  
          }  
        \],  
        "parameters": \[  
          {  
            "in": "path",  
            "name": "accountId",  
            "schema": {  
              "type": "string",  
              "format": "uuid"  
            },  
            "required": true,  
            "description": "Unique ID of the account."  
          }  
        \],  
        "responses": {  
          "200": {  
            "description": "Account details retrieved successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "$ref": "\#/components/schemas/Account"  
                }  
              }  
            }  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "404": {  
            "$ref": "\#/components/responses/NotFoundError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/accounts/{accountId}/balance-history": {  
      "get": {  
        "tags": \[  
          "Accounts",  
          "Audit & Reporting"  
        \],  
        "summary": "Get account balance history",  
        "description": "Retrieve the historical balances (ledger, available, locked) for a specific account.",  
        "operationId": "getAccountBalanceHistory",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "account.read.balance\_history"  
            \]  
          }  
        \],  
        "parameters": \[  
          {  
            "in": "path",  
            "name": "accountId",  
            "schema": {  
              "type": "string",  
              "format": "uuid"  
            },  
            "required": true,  
            "description": "Unique ID of the account."  
          },  
          {  
            "in": "query",  
            "name": "from\_date",  
            "schema": {  
              "type": "string",  
              "format": "date-time"  
            },  
            "description": "Start date (inclusive) for history."  
          },  
          {  
            "in": "query",  
            "name": "to\_date",  
            "schema": {  
              "type": "string",  
              "format": "date-time"  
            },  
            "description": "End date (inclusive) for history."  
          }  
        \],  
        "responses": {  
          "200": {  
            "description": "Account balance history retrieved successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "type": "object",  
                  "properties": {  
                    "data": {  
                      "type": "array",  
                      "items": {  
                        "$ref": "\#/components/schemas/AccountBalanceHistory"  
                      }  
                    }  
                  }  
                }  
              }  
            }  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "404": {  
            "$ref": "\#/components/responses/NotFoundError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/transactions/deposit": {  
      "post": {  
        "tags": \[  
          "Transactions"  
        \],  
        "summary": "Process a deposit",  
        "description": "Initiate a deposit transaction to a specified account.",  
        "operationId": "processDeposit",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "transaction.deposit"  
            \]  
          }  
        \],  
        "requestBody": {  
          "required": true,  
          "content": {  
            "application/json": {  
              "schema": {  
                "$ref": "\#/components/schemas/DepositRequest"  
              }  
            }  
          }  
        },  
        "responses": {  
          "202": {  
            "description": "Deposit request accepted for processing.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "$ref": "\#/components/schemas/TransactionStatusResponse"  
                }  
              }  
            }  
          },  
          "400": {  
            "$ref": "\#/components/responses/BadRequestError"  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "422": {  
            "$ref": "\#/components/responses/ValidationError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/transactions/{transactionId}": {  
      "get": {  
        "tags": \[  
          "Transactions"  
        \],  
        "summary": "Get transaction details",  
        "description": "Retrieve details for a specific transaction, including pre and post balances.",  
        "operationId": "getTransactionById",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "transaction.read"  
            \]  
          }  
        \],  
        "parameters": \[  
          {  
            "in": "path",  
            "name": "transactionId",  
            "schema": {  
              "type": "string",  
              "format": "uuid"  
            },  
            "required": true,  
            "description": "Unique ID of the transaction."  
          }  
        \],  
        "responses": {  
          "200": {  
            "description": "Transaction details retrieved successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "$ref": "\#/components/schemas/Transaction"  
                }  
              }  
            }  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "404": {  
            "$ref": "\#/components/responses/NotFoundError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/kyc-levels": {  
      "get": {  
        "tags": \[  
          "KYC"  
        \],  
        "summary": "List all KYC Levels",  
        "description": "Retrieve a list of all defined KYC levels.",  
        "operationId": "listKycLevels",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "kyc.read.levels"  
            \]  
          }  
        \],  
        "responses": {  
          "200": {  
            "description": "List of KYC levels retrieved successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "type": "object",  
                  "properties": {  
                    "data": {  
                      "type": "array",  
                      "items": {  
                        "$ref": "\#/components/schemas/KycLevel"  
                      }  
                    }  
                  }  
                }  
              }  
            }  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    },  
    "/kyc-levels/{kycLevelId}/configurations": {  
      "get": {  
        "tags": \[  
          "KYC"  
        \],  
        "summary": "Get KYC Level configurations",  
        "description": "Retrieve the specific limits and rules for a given KYC Level and currency.",  
        "operationId": "getKycLevelConfigurations",  
        "security": \[  
          {  
            "bearerAuth": \[  
              "kyc.read.configurations"  
            \]  
          }  
        \],  
        "parameters": \[  
          {  
            "in": "path",  
            "name": "kycLevelId",  
            "schema": {  
              "type": "string",  
              "format": "uuid"  
            },  
            "required": true,  
            "description": "Unique ID of the KYC Level."  
          },  
          {  
            "in": "query",  
            "name": "currency",  
            "schema": {  
              "type": "string",  
              "pattern": "^\[A-Z\]{3}$",  
              "example": "USD"  
            },  
            "description": "Currency code (ISO 4217\) for which to retrieve configurations."  
          }  
        \],  
        "responses": {  
          "200": {  
            "description": "KYC Level configurations retrieved successfully.",  
            "content": {  
              "application/json": {  
                "schema": {  
                  "$ref": "\#/components/schemas/KycLevelConfiguration"  
                }  
              }  
            }  
          },  
          "401": {  
            "$ref": "\#/components/responses/UnauthorizedError"  
          },  
          "403": {  
            "$ref": "\#/components/responses/ForbiddenError"  
          },  
          "404": {  
            "$ref": "\#/components/responses/NotFoundError"  
          },  
          "500": {  
            "$ref": "\#/components/responses/ServerError"  
          }  
        }  
      }  
    }  
  },  
  "components": {  
    "securitySchemes": {  
      "bearerAuth": {  
        "type": "http",  
        "scheme": "bearer",  
        "bearerFormat": "JWT",  
        "description": "JWT Token obtained after successful login."  
      }  
    },  
    "schemas": {  
      "PaginationMeta": {  
        "type": "object",  
        "properties": {  
          "total": {  
            "type": "integer",  
            "description": "Total number of records."  
          },  
          "per\_page": {  
            "type": "integer",  
            "description": "Number of records per page."  
          },  
          "current\_page": {  
            "type": "integer",  
            "description": "Current page number."  
          },  
          "last\_page": {  
            "type": "integer",  
            "description": "Last page number."  
          },  
          "from": {  
            "type": "integer",  
            "description": "Starting record number for the current page."  
          },  
          "to": {  
            "type": "integer",  
            "description": "Ending record number for the current page."  
          }  
        }  
      },  
      "Error": {  
        "type": "object",  
        "required": \[  
          "code",  
          "message"  
        \],  
        "properties": {  
          "code": {  
            "type": "string",  
            "description": "A unique error code for programmatic identification.",  
            "example": "VALIDATION\_ERROR"  
          },  
          "message": {  
            "type": "string",  
            "description": "A human-readable description of the error.",  
            "example": "The provided data failed validation."  
          },  
          "details": {  
            "type": "object",  
            "nullable": true,  
            "description": "Optional detailed error information, e.g., field-level validation errors."  
          }  
        }  
      },  
      "Client": {  
        "type": "object",  
        "properties": {  
          "id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "client\_type": {  
            "type": "string",  
            "enum": \[  
              "individual",  
              "organization"  
            \]  
          },  
          "organization\_business\_id": {  
            "type": "string",  
            "format": "uuid",  
            "nullable": true,  
            "description": "Required if client\_type is 'organization'. Foreign key to BusinessEntityInformation."  
          },  
          "first\_name": {  
            "type": "string",  
            "nullable": true,  
            "description": "Nullable if client\_type is 'organization'."  
          },  
          "last\_name": {  
            "type": "string",  
            "nullable": true,  
            "description": "Nullable if client\_type is 'organization'."  
          },  
          "date\_of\_birth": {  
            "type": "string",  
            "format": "date",  
            "nullable": true,  
            "description": "Nullable if client\_type is 'organization'."  
          },  
          "gender": {  
            "type": "string",  
            "enum": \[  
              "male",  
              "female",  
              "other"  
            \],  
            "nullable": true  
          },  
          "marital\_status": {  
            "type": "string",  
            "nullable": true  
          },  
          "nationality": {  
            "type": "string",  
            "description": "ISO 3166-1 alpha-3 code",  
            "nullable": true  
          },  
          "email": {  
            "type": "string",  
            "format": "email",  
            "unique": true,  
            "nullable": true  
          },  
          "phone\_number": {  
            "type": "string",  
            "unique": true,  
            "nullable": true  
          },  
          "address": {  
            "type": "object",  
            "properties": {  
              "street": {  
                "type": "string"  
              },  
              "city": {  
                "type": "string"  
              },  
              "state": {  
                "type": "string"  
              },  
              "postal\_code": {  
                "type": "string"  
              },  
              "country": {  
                "type": "string"  
              }  
            },  
            "nullable": true  
          },  
          "status": {  
            "type": "string",  
            "enum": \[  
              "pending\_kyc",  
              "active",  
              "inactive",  
              "suspended",  
              "closed"  
            \]  
          },  
          "kyc\_status": {  
            "type": "string",  
            "enum": \[  
              "pending",  
              "verified",  
              "rejected"  
            \]  
          },  
          "created\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          },  
          "updated\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          }  
        },  
        "required": \[  
          "client\_type",  
          "status",  
          "kyc\_status"  
        \]  
      },  
      "ClientCreateRequest": {  
        "type": "object",  
        "properties": {  
          "client\_type": {  
            "type": "string",  
            "enum": \[  
              "individual",  
              "organization"  
            \],  
            "description": "Type of client to create."  
          },  
          "organization\_business\_id": {  
            "type": "string",  
            "format": "uuid",  
            "nullable": true,  
            "description": "Required if client\_type is 'organization'. Foreign key to BusinessEntityInformation."  
          },  
          "first\_name": {  
            "type": "string",  
            "nullable": true  
          },  
          "last\_name": {  
            "type": "string",  
            "nullable": true  
          },  
          "date\_of\_birth": {  
            "type": "string",  
            "format": "date",  
            "nullable": true  
          },  
          "gender": {  
            "type": "string",  
            "enum": \[  
              "male",  
              "female",  
              "other"  
            \],  
            "nullable": true  
          },  
          "marital\_status": {  
            "type": "string",  
            "nullable": true  
          },  
          "nationality": {  
            "type": "string",  
            "description": "ISO 3166-1 alpha-3 code",  
            "nullable": true  
          },  
          "email": {  
            "type": "string",  
            "format": "email",  
            "nullable": true  
          },  
          "phone\_number": {  
            "type": "string",  
            "nullable": true  
          },  
          "address": {  
            "type": "object",  
            "properties": {  
              "street": {  
                "type": "string"  
              },  
              "city": {  
                "type": "string"  
              },  
              "state": {  
                "type": "string"  
              },  
              "postal\_code": {  
                "type": "string"  
              },  
              "country": {  
                "type": "string"  
              }  
            },  
            "nullable": true  
          }  
        },  
        "required": \[  
          "client\_type"  
        \]  
      },  
      "ClientUpdateRequest": {  
        "type": "object",  
        "properties": {  
          "first\_name": {  
            "type": "string",  
            "nullable": true  
          },  
          "last\_name": {  
            "type": "string",  
            "nullable": true  
          },  
          "date\_of\_birth": {  
            "type": "string",  
            "format": "date",  
            "nullable": true  
          },  
          "gender": {  
            "type": "string",  
            "enum": \[  
              "male",  
              "female",  
              "other"  
            \],  
            "nullable": true  
          },  
          "marital\_status": {  
            "type": "string",  
            "nullable": true  
          },  
          "nationality": {  
            "type": "string",  
            "nullable": true  
          },  
          "email": {  
            "type": "string",  
            "format": "email",  
            "nullable": true  
          },  
          "phone\_number": {  
            "type": "string",  
            "nullable": true  
          },  
          "address": {  
            "type": "object",  
            "properties": {  
              "street": {  
                "type": "string"  
              },  
              "city": {  
                "type": "string"  
              },  
              "state": {  
                "type": "string"  
              },  
              "postal\_code": {  
                "type": "string"  
              },  
              "country": {  
                "type": "string"  
              }  
            },  
            "nullable": true  
          },  
          "status": {  
            "type": "string",  
            "enum": \[  
              "active",  
              "inactive",  
              "suspended",  
              "closed"  
            \],  
            "nullable": true  
          },  
          "kyc\_status": {  
            "type": "string",  
            "enum": \[  
              "pending",  
              "verified",  
              "rejected"  
            \],  
            "nullable": true  
          }  
        }  
      },  
      "ClientKycStatusHistory": {  
        "type": "object",  
        "properties": {  
          "id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "client\_id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "old\_status": {  
            "type": "string",  
            "enum": \[  
              "pending",  
              "verified",  
              "rejected"  
            \]  
          },  
          "new\_status": {  
            "type": "string",  
            "enum": \[  
              "pending",  
              "verified",  
              "rejected"  
            \]  
          },  
          "action\_by\_user\_id": {  
            "type": "string",  
            "format": "uuid",  
            "nullable": true,  
            "description": "ID of the user who performed the action, or null if system action."  
          },  
          "notes": {  
            "type": "string",  
            "nullable": true  
          },  
          "changed\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          }  
        },  
        "required": \[  
          "client\_id",  
          "old\_status",  
          "new\_status",  
          "changed\_at"  
        \]  
      },  
      "KycLevel": {  
        "type": "object",  
        "properties": {  
          "id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "level\_name": {  
            "type": "string",  
            "description": "e.g., 'Level 1', 'Level 2'"  
          },  
          "description": {  
            "type": "string",  
            "nullable": true  
          },  
          "order": {  
            "type": "integer",  
            "description": "Numerical order of the KYC level."  
          },  
          "is\_active": {  
            "type": "boolean"  
          },  
          "created\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          },  
          "updated\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          }  
        },  
        "required": \[  
          "level\_name",  
          "order",  
          "is\_active"  
        \]  
      },  
      "KycLevelConfiguration": {  
        "type": "object",  
        "properties": {  
          "id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "kyc\_level\_id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "currency": {  
            "type": "string",  
            "pattern": "^\[A-Z\]{3}$",  
            "description": "ISO 4217 currency code."  
          },  
          "maximum\_balance": {  
            "type": "number",  
            "format": "float",  
            "nullable": true,  
            "description": "Maximum allowed balance on account for this KYC level and currency."  
          },  
          "minimum\_balance": {  
            "type": "number",  
            "format": "float",  
            "nullable": true,  
            "description": "Minimum required balance on account for this KYC level and currency."  
          },  
          "single\_transaction\_limit": {  
            "type": "number",  
            "format": "float",  
            "nullable": true,  
            "description": "Maximum amount for a single transaction."  
          },  
          "daily\_transaction\_limit": {  
            "type": "number",  
            "format": "float",  
            "nullable": true,  
            "description": "Maximum aggregate amount for transactions in a day."  
          },  
          "monthly\_transaction\_limit": {  
            "type": "number",  
            "format": "float",  
            "nullable": true,  
            "description": "Maximum aggregate amount for transactions in a month."  
          },  
          "annual\_transaction\_limit": {  
            "type": "number",  
            "format": "float",  
            "nullable": true,  
            "description": "Maximum aggregate amount for transactions in a year."  
          },  
          "created\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          },  
          "updated\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          }  
        },  
        "required": \[  
          "kyc\_level\_id",  
          "currency"  
        \]  
      },  
      "Account": {  
        "type": "object",  
        "properties": {  
          "id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "account\_number": {  
            "type": "string",  
            "readOnly": true  
          },  
          "account\_name": {  
            "type": "string"  
          },  
          "client\_id": {  
            "type": "string",  
            "format": "uuid"  
          },  
          "product\_id": {  
            "type": "string",  
            "format": "uuid"  
          },  
          "kyc\_level\_id": {  
            "type": "string",  
            "format": "uuid",  
            "description": "The KYC level assigned to this account."  
          },  
          "currency": {  
            "type": "string",  
            "pattern": "^\[A-Z\]{3}$",  
            "description": "ISO 4217 currency code."  
          },  
          "ledger\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Current actual balance as per GL.",  
            "readOnly": true  
          },  
          "available\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Balance available for spending (ledger \- locked).",  
            "readOnly": true  
          },  
          "locked\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Amount held/frozen on the account.",  
            "readOnly": true  
          },  
          "status": {  
            "type": "string",  
            "enum": \[  
              "pending\_activation",  
              "active",  
              "dormant",  
              "suspended",  
              "closed"  
            \]  
          },  
          "activation\_date": {  
            "type": "string",  
            "format": "date-time",  
            "nullable": true  
          },  
          "closure\_date": {  
            "type": "string",  
            "format": "date-time",  
            "nullable": true  
          },  
          "created\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          },  
          "updated\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          }  
        },  
        "required": \[  
          "account\_number",  
          "account\_name",  
          "client\_id",  
          "product\_id",  
          "kyc\_level\_id",  
          "currency",  
          "ledger\_balance",  
          "available\_balance",  
          "locked\_balance",  
          "status"  
        \]  
      },  
      "AccountCreateRequest": {  
        "type": "object",  
        "properties": {  
          "client\_id": {  
            "type": "string",  
            "format": "uuid",  
            "description": "The client ID to whom the account belongs."  
          },  
          "account\_name": {  
            "type": "string",  
            "description": "A user-friendly name for the account."  
          },  
          "product\_id": {  
            "type": "string",  
            "format": "uuid",  
            "description": "The product ID associated with this account (e.g., Savings Product A)."  
          },  
          "kyc\_level\_id": {  
            "type": "string",  
            "format": "uuid",  
            "description": "The KYC level ID for the account. Should align with client's KYC."  
          },  
          "currency": {  
            "type": "string",  
            "pattern": "^\[A-Z\]{3}$",  
            "description": "ISO 4217 currency code."  
          },  
          "initial\_deposit\_amount": {  
            "type": "number",  
            "format": "float",  
            "minimum": 0.01,  
            "nullable": true,  
            "description": "Optional initial deposit amount."  
          }  
        },  
        "required": \[  
          "client\_id",  
          "account\_name",  
          "product\_id",  
          "kyc\_level\_id",  
          "currency"  
        \]  
      },  
      "AccountBalanceHistory": {  
        "type": "object",  
        "properties": {  
          "id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "account\_id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "ledger\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Ledger balance at the time of snapshot."  
          },  
          "available\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Available balance at the time of snapshot."  
          },  
          "locked\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Locked balance at the time of snapshot."  
          },  
          "balance\_date": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          },  
          "transaction\_id": {  
            "type": "string",  
            "format": "uuid",  
            "nullable": true,  
            "description": "ID of the transaction that caused this balance snapshot, if applicable."  
          },  
          "description": {  
            "type": "string",  
            "nullable": true,  
            "description": "A short description of the balance change (e.g., \\"Initial Balance\\", \\"Monthly Interest\\", \\"Withdrawal\\")."  
          },  
          "created\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          },  
          "updated\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          }  
        },  
        "required": \[  
          "account\_id",  
          "ledger\_balance",  
          "available\_balance",  
          "locked\_balance",  
          "balance\_date"  
        \]  
      },  
      "Transaction": {  
        "type": "object",  
        "properties": {  
          "id": {  
            "type": "string",  
            "format": "uuid",  
            "readOnly": true  
          },  
          "transaction\_type": {  
            "type": "string",  
            "enum": \[  
              "deposit",  
              "withdrawal",  
              "internal\_transfer",  
              "external\_transfer",  
              "loan\_disbursement",  
              "loan\_repayment",  
              "fee\_collection",  
              "interest\_posting",  
              "reversal",  
              "chargeback"  
            \]  
          },  
          "account\_id": {  
            "type": "string",  
            "format": "uuid",  
            "description": "The primary account involved in the transaction."  
          },  
          "amount": {  
            "type": "number",  
            "format": "float"  
          },  
          "currency": {  
            "type": "string",  
            "pattern": "^\[A-Z\]{3}$",  
            "description": "ISO 4217 currency code."  
          },  
          "transaction\_date": {  
            "type": "string",  
            "format": "date-time"  
          },  
          "status": {  
            "type": "string",  
            "enum": \[  
              "pending",  
              "completed",  
              "failed",  
              "reversed",  
              "on\_hold"  
            \]  
          },  
          "description": {  
            "type": "string",  
            "nullable": true  
          },  
          "reference\_id": {  
            "type": "string",  
            "nullable": true,  
            "description": "External reference ID for the transaction."  
          },  
          "channel": {  
            "type": "string",  
            "nullable": true  
          },  
          "related\_transaction\_id": {  
            "type": "string",  
            "format": "uuid",  
            "nullable": true,  
            "description": "For reversals, points to the original transaction."  
          },  
          "processed\_by\_user\_id": {  
            "type": "string",  
            "format": "uuid",  
            "nullable": true,  
            "description": "User ID who processed the transaction, or null if system-initiated."  
          },  
          "pre\_transaction\_ledger\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Ledger balance of account\_id before this transaction.",  
            "readOnly": true  
          },  
          "post\_transaction\_ledger\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Ledger balance of account\_id after this transaction.",  
            "readOnly": true  
          },  
          "pre\_transaction\_available\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Available balance of account\_id before this transaction.",  
            "readOnly": true  
          },  
          "post\_transaction\_available\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Available balance of account\_id after this transaction.",  
            "readOnly": true  
          },  
          "pre\_transaction\_locked\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Locked balance of account\_id before this transaction.",  
            "readOnly": true  
          },  
          "post\_transaction\_locked\_balance": {  
            "type": "number",  
            "format": "float",  
            "description": "Locked balance of account\_id after this transaction.",  
            "readOnly": true  
          },  
          "created\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          },  
          "updated\_at": {  
            "type": "string",  
            "format": "date-time",  
            "readOnly": true  
          }  
        },  
        "required": \[  
          "transaction\_type",  
          "account\_id",  
          "amount",  
          "currency",  
          "transaction\_date",  
          "status",  
          "pre\_transaction\_ledger\_balance",  
          "post\_transaction\_ledger\_balance",  
          "pre\_transaction\_available\_balance",  
          "post\_transaction\_available\_balance",  
          "pre\_transaction\_locked\_balance",  
          "post\_transaction\_locked\_balance"  
        \]  
      },  
      "DepositRequest": {  
        "type": "object",  
        "properties": {  
          "account\_id": {  
            "type": "string",  
            "format": "uuid",  
            "description": "The ID of the account to deposit into."  
          },  
          "amount": {  
            "type": "number",  
            "format": "float",  
            "minimum": 0.01,  
            "description": "The amount to deposit."  
          },  
          "currency": {  
            "type": "string",  
            "pattern": "^\[A-Z\]{3}$",  
            "description": "ISO 4217 currency code."  
          },  
          "description": {  
            "type": "string",  
            "nullable": true,  
            "description": "Optional description for the deposit."  
          },  
          "channel": {  
            "type": "string",  
            "description": "Channel of the deposit (e.g., 'cash', 'transfer', 'mobile\_money')."  
          },  
          "reference\_id": {  
            "type": "string",  
            "nullable": true,  
            "description": "Optional external reference ID for the deposit."  
          }  
        },  
        "required": \[  
          "account\_id",  
          "amount",  
          "currency",  
          "channel"  
        \]  
      },  
      "TransactionStatusResponse": {  
        "type": "object",  
        "properties": {  
          "transaction\_id": {  
            "type": "string",  
            "format": "uuid",  
            "description": "Unique ID of the created transaction."  
          },  
          "status": {  
            "type": "string",  
            "enum": \[  
              "pending",  
              "completed",  
              "failed",  
              "reversed",  
              "on\_hold"  
            \],  
            "description": "Current status of the transaction."  
          },  
          "message": {  
            "type": "string",  
            "description": "A descriptive message about the transaction status."  
          }  
        },  
        "required": \[  
          "transaction\_id",  
          "status",  
          "message"  
        \]  
      }  
    },  
    "responses": {  
      "UnauthorizedError": {  
        "description": "Authentication failed or token is invalid.",  
        "content": {  
          "application/json": {  
            "schema": {  
              "$ref": "\#/components/schemas/Error",  
              "example": {  
                "code": "UNAUTHORIZED\_ACCESS",  
                "message": "Authentication required or invalid token."  
              }  
            }  
          }  
        }  
      },  
      "ForbiddenError": {  
        "description": "Not enough permissions to perform the action.",  
        "content": {  
          "application/json": {  
            "schema": {  
              "$ref": "\#/components/schemas/Error",  
              "example": {  
                "code": "FORBIDDEN\_ACCESS",  
                "message": "You do not have the necessary permissions to access this resource."  
              }  
            }  
          }  
        }  
      },  
      "NotFoundError": {  
        "description": "The specified resource was not found.",  
        "content": {  
          "application/json": {  
            "schema": {  
              "$ref": "\#/components/schemas/Error",  
              "example": {  
                "code": "RESOURCE\_NOT\_FOUND",  
                "message": "The client with ID 'xyz' was not found."  
              }  
            }  
          }  
        }  
      },  
      "BadRequestError": {  
        "description": "The request was malformed or could not be understood.",  
        "content": {  
          "application/json": {  
            "schema": {  
              "$ref": "\#/components/schemas/Error",  
              "example": {  
                "code": "BAD\_REQUEST",  
                "message": "The request payload is invalid."  
              }  
            }  
          }  
        }  
      },  
      "ValidationError": {  
        "description": "One or more validation errors occurred.",  
        "content": {  
          "application/json": {  
            "schema": {  
              "$ref": "\#/components/schemas/Error",  
              "example": {  
                "code": "VALIDATION\_ERROR",  
                "message": "The provided data failed validation.",  
                "details": {  
                  "email": "The email format is invalid.",  
                  "amount": "Amount must be greater than zero."  
                }  
              }  
            }  
          }  
        }  
      },  
      "ServerError": {  
        "description": "An unexpected server error occurred.",  
        "content": {  
          "application/json": {  
            "schema": {  
              "$ref": "\#/components/schemas/Error",  
              "example": {  
                "code": "INTERNAL\_SERVER\_ERROR",  
                "message": "An unexpected error occurred on the server. Please try again later."  
              }  
            }  
          "application/json": {  
            "schema": {  
              "$ref": "\#/components/schemas/Error",  
              "example": {  
                "code": "INTERNAL\_SERVER\_ERROR",  
                "message": "An unexpected error occurred on the server. Please try again later."  
              }  
            }  
          }  
        }  
      }  
    }  
  }  
}

