<?php namespace Dalnix\GraphQLGenerator;

use GraphQL\Type\SchemaConfig;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Dalnix\GraphQLGenerator\Command\GenerateType;
use Folklore\GraphQL\Support\Facades\GraphQL;

class GraphQLGeneratorProvider extends ServiceProvider
{
    protected $files;
    public function __construct(Filesystem $files) {
        parent::__construct();
        $this->files = $files;
    }
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateType::class
            ]);
        }
    }

    public function getModels($dir, $namespace)
    {
        $dir = $dir;
        $files = scandir($dir);

        $models = array();
        $namespace = $namespace;

        foreach ($files as $file) {
            //skip current and parent folder entries and non-php files
            if ($file == '.' || $file == '..' || !strpos($file, '.php')) {
                continue;
            }
            $models[] = $namespace . substr($file, 0, -4);
        }

        return $models;
    }

    public function boot()
    {

        $this->publishes([
            __DIR__ . '/config/graphql_generator.php' => config_path('graphql_generator.php'),
        ]);
        if (!$this->files->isDirectory('app/GraphQL')) {
            $this->files->makeDirectory('app/GraphQL');
        }
        $types =  $this->getModels(app_path("") . '/GraphQL/Type', 'App\\GraphQL\\Type\\');
        $mutations =  $this->getModels(app_path("") . '/GraphQL/Mutation', 'App\\GraphQL\\Mutation\\');
        $queries =  $this->getModels(app_path("") . '/GraphQL/Query', 'App\\GraphQL\\Query\\');
        $correct_types = [];
        $correct_mutations = [];

        foreach ($types as $type) {
            $class_type = new $type();
            $attr = $class_type->getAttributes();
            $correct_types[str_replace('App\\GraphQL\\Type\\', '', $type)] = $type;
        }

        foreach ($mutations as $m) {
            $class_type = new $m();
            $correct_mutations[] = $m;
        }
        $correct_queries = [];
        foreach ($queries as $q) {
            $class_type = new $q();
            $correct_queries[] = $q;
        }

        $schema = GraphQL::schema([
            'types' => $correct_types,
            'query' => $correct_queries,
            'mutation' => $correct_mutations
        ]);

        GraphQL::addSchema(config('graphql_generator')['schema_name'], ['query' => $correct_queries,'mutation' => $correct_mutations]);

    }
}