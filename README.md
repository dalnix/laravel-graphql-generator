# Laravel GraphQL Generator

Note: work in progress

This package will help you to generate Types, Queries and Mutations for [Folkloreatelier/laravel-graphql](https://github.com/Folkloreatelier/laravel-graphql)'s package.

### Installation

Install composer package

```sh
$ composer install dalnix/laravel-graphql-generator
```
Use following command to generate your Types, Queries or Mutations, files will be created in app/GraphQL/Type,  app/GraphQL/Mutation or app/GraphQL/Query. When creating a new Type, all columns of the table in the databasewill be imported, if you want to remove some fields that should'nt be able to access via GraphQL, edit your Type fields method.
```sh
$ php artisan dalnix:graphql:make
```
Publish config to change your GraphQL schema
```sh
$ php artisan vendor:publish
```

Test your queries or mutations: (app_url)/graphiql/custom (custom is the default laravel graphql generator schema)

### Notes
Laravel graphql generator does not generate complete functioning resolve functions for your queries or mutations, you have to manually edit your queries or mutations to be able to return data.

You can't add relations via the command, to do so you must edit your Types in order to be able to make queries with eager loaded relations, see [laravel-graphql's documention on eager loading relationships](https://github.com/Folkloreatelier/laravel-graphql/blob/develop/docs/advanced.md#eager-loading-relationships)

### Todos

 - Make it possible to add relations directly in the command

License
----

AGPL

