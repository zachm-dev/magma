# Magma (Laravel 4 Package)


> Warning! This package is in early beta and will change a lot.


Magma is a Laravel package for creating powerful and dynamic controllers. Controllers using Magma should contain a lot less code, while being more descriptive and allowing for more functionality. Magma abstracts away a lot of the repeating code commonly found in application controllers. 


## Features

**Current:**

- Dynamic Eloquent queries through url parameters (order by, skip, take, where, with)
- Integrates with Ardent
- Static methods for smart operations on models
- Deleting a resource automatically cleans up relations that should be deleted (similar to on delete cascade)
- Automatically creates / updates relations if they exist in post/put payload

**Future:**

- Figure out how to integrate Entrust role/permission authorization with validation. Ability to set on controllers, models, fields, field values
- Decouple from Ardent, so package just relies on Eloquent
- Make errors more flexible instead of just returning json error response


## Installation

Add the package to 'require' in your composer.json file:

    "require": {
        "jbizzay/magma": "dev-master"
    },

Run 'composer dump-autoload' from the command line:

    #composer dump-autoload
    

## Usage

### Models

Models should extend Ardent and define their relationships using 

    public static $relationsData = [];

That's all it takes to make models work with Magma, and you can use normal Ardent features like hydration and validation.


### Query

Call Magma::query from your controller or route

    return Magma::query('User');

** Parameters**

- string Model name to query

** Query parameters**

- order_by - Order results ( order_by=username,asc )
- skip - Skip records ( skip=10 )
- take - Limit # of records ( limit=100 )
- where - Add where to query ( where[]=status,1 )
- with - Load related data ( with[]=roles&with[]=image )



### Show

Call Magma::find from your controller or route

    return Magma::find('User', $id);

**Parameters**

- string Model name to retrieve
- integer Model ID

**Query Parameters**

- with[] - Load related data, e.g. with[]=roles&with[]=image

Response is the model record retrieved, or json error response if not found.



### Store

Call Magma::store from your controller or route

    return Magma::store('User');

or 

    return Magma::store('User', [], function ($user) {
        // Do something in saved callback
    }, function ($user) {
        // Do something in error callback
    });

**Parameters**

- string Model name to create
- array Parameters to set on the model. Also overrides anything set in Input
- function Success callback
- function Error callback

Response is the model record created. If related models were attached, those will also be attached to the model record. If validation failed, response will be a json response of the error messages.

When posting like this: POST /api/users with post data: username=test&email=test@mail.net&password=password&password_confirmation=password&roles[]=1&roles[]=2&image[]=123 , if the User model has $relationsData setup for roles and image, the user record will also sync these role ids and attach the image.



### Update

Call Magma::update from your controller or route

    return Magme::update('User', 123);

or

    return Magma::update('User', 123, [], function ($user), {
        // Do something in saved callback
    }, function ($user) {
        // Do something in error callback
    });

**Parameters**

- string Model name to update
- integer Model ID
- array Parameters to set on the model. Also overrides anything set in Input
- function Success callback
- function Error callback


### Delete

Call Magma::destroy from your controller or route. Magma will also detach from related records

    return Magma::destroy('User', 123);

or

    return Magma::destroy('User', 123, function ($user) {
        // Do something on success
    });

**Parameters**

- string Model name to delete
- integer Model ID
- function Success callback

