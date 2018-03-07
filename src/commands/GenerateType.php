<?php namespace Dalnix\GraphQLGenerator\Command;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class GenerateType extends Command
{
    protected $signature = 'dalnix:graphql:make';

    protected $description = 'Make a type for graphQL';

    protected $files;

    protected $model = null;

    protected $type_args = [
        'className' => null,
        'name' => null,
        'description' => null,
        'fields' => [],
        'graphQLType' => null,
        'args' => [],
        'models' => []
    ];

    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
     * @param Modules $module
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Load all models under App\
     * @param $dir
     * @param $namespace
     * @return array
     */

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

    /**
     * Handle method
     */

    public function handle()
    {
        $type = $this->choice('What do you want to create?', ['Type', 'Query', 'Mutation'], 0);
        if ($type == 'Type') {
            $this->info('Creating type');
            $this->info('--------------------');
            $this->createType();
        } elseif ($type == 'Query') {
            $this->info('Creating query');
            $this->info('--------------------');
            $this->createQuery();

        } else {
            $this->info('Creating mutation');
            $this->info('--------------------');
            $this->createMutation();
        }
    }

    /**
     * This method collects the data needed to create a mutation
     */
    public function createMutation()
    {
        $models = $this->getModels(app_path(""), "App\\");
        $more_models = true;
        do {
            $this->type_args['models'][] = $this->choice('What model do u want to include (use App\\Model)?', $models,
                0);
            if (!$this->confirm('Include more models?')) {
                $more_models = false;
            }
        } while ($more_models);

        $this->type_args['className'] = $this->ask('Name of class?');
        $this->info('Class name is: ' . $this->type_args['className']);
        $this->type_args['name'] = $this->ask('Name of type?');
        $this->info('Type name is: ' . $this->type_args['name']);
        $this->type_args['description'] = $this->ask('Description of type?');
        $this->info('Type description is: ' . $this->type_args['description']);
        $models = $this->getModels(app_path("") . '/GraphQL/Type', '');
        $this->type_args['graphQLType'] = $this->choice('What GraphQL type is this?', $models, 0);
        $continue = true;
        $this->info('Add variables for your query');
        if (!$this->confirm('Do you need to add variables?')) {
            $continue = false;
        }
        while ($continue) {
            $arg = [];

            $name = $this->ask('Variable name?');
            $type = $this->choice('Variable type', ['Int', 'String'], 1);
            $rule = $this->ask('Rules (write rules like ["email", "unique:users"]');

            $this->type_args['args'][$name] = [
                'type' => $type = '--Type::nonNull(Type::' . strtolower($type) . '())--',
                'rules' => $rule
            ];
            if (!$this->confirm('Add another variable?')) {
                $continue = false;
            }
        }

        if ($this->confirm('Generate query?')) {
            $this->generateMutation();
        }
    }

    /**
     * This method generates the mutation
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */

    public function generateMutation()
    {
        if (!$this->files->isDirectory('app/GraphQL')) {
            $this->files->makeDirectory('app/GraphQL');

        }
        if (!$this->files->isDirectory('app/GraphQL/Mutation')) {
            $this->files->makeDirectory('app/GraphQL/Mutation');
        }
        $pathMap = [];
        $directory = 'app/GraphQL/Mutation';
        $source = __DIR__ . '/stubs/Mutation.stub';

        $file = $this->files->get($source);

        $contents = $this->replacePlaceholdersMutation($file);

        $filePath = $directory;
        $dir = dirname($filePath);

        if (!$this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }
        $this->files->put($filePath . '/' . $this->type_args['className'] . '.php', $contents);


        $this->info("\nGraphQL mutation generated successfully.");
        $this->info("\n--------------------------------------");
        $this->info("\nYou have to edit your new file and change the resolve method to do the mutation that you want it to do and then return it.");
    }

    /**
     * This method collects the data needed to create a query
     */
    public function createQuery()
    {
        $models = $this->getModels(app_path(""), "App\\");
        $name = $this->choice('What model do u want to use?', $models, 0);

        $this->info('Your choice: ' . $name);
        $this->model = $name;
        $this->type_args['className'] = $this->ask('Name of class?');
        $this->info('Class name is: ' . $this->type_args['className']);
        $this->type_args['name'] = $this->ask('Name of type?');
        $this->info('Type name is: ' . $this->type_args['name']);
        $this->type_args['description'] = $this->ask('Description of type?');
        $this->info('Type description is: ' . $this->type_args['description']);

        $models = $this->getModels(app_path("") . '/GraphQL/Type', '');
        $this->type_args['graphQLType'] = $this->choice('What GraphQL type is this?', $models, 0);
        $continue = true;
        $this->info('Add variables for your query');
        if (!$this->confirm('Do you need to add variables?')) {
            $continue = false;
        }
        while ($continue) {
            $arg = [];

            $name = $this->ask('Variable name?');
            $type = $this->choice('Variable type', ['Int', 'String'], 1);

            $this->type_args['args'][$name] = ['type' => $type = '--Type::nonNull(Type::' . strtolower($type) . '())--'];
            if (!$this->confirm('Add another variable?')) {
                $continue = false;
            }
        }

        if ($this->confirm('Generate query?')) {
            $this->generateQuery();
        }
    }

    /**
     * This method generates the query
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function generateQuery()
    {
        if (!$this->files->isDirectory('app/GraphQL')) {
            $this->files->makeDirectory('app/GraphQL');
            if (!$this->files->isDirectory('app/GraphQL/Query')) {
                $this->files->makeDirectory('app/GraphQL/Query');
            }
        }

        $pathMap = [];
        $directory = 'app/GraphQL/Query';
        $source = __DIR__ . '/stubs/Query.stub';

        $file = $this->files->get($source);

        $contents = $this->replacePlaceholdersQuery($file);

        $filePath = $directory;
        $dir = dirname($filePath);

        if (!$this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }
        $this->files->put($filePath . '/' . $this->type_args['className'] . '.php', $contents);


        $this->info("\nGraphQL query generated successfully.");
        $this->info("\n--------------------------------------");
        $this->info("\nYou have to edit your new file and change the resolve method to do the query that you want it to do and then return it.");

    }

    /**
     * This method collects the data needed to create a type
     */
    public function createType()
    {
        $models = $this->getModels(app_path(""), "App\\");
        $name = $this->choice('What model do u want to use?', $models, 0);

        $this->info('Your choice: ' . $name);
        $this->model = $name;
        $this->type_args['className'] = $this->ask('Name of class?');
        $this->info('Class name is: ' . $this->type_args['className']);
        $this->type_args['name'] = $this->ask('Name of type?');
        $this->info('Type name is: ' . $this->type_args['name']);
        $this->type_args['description'] = $this->ask('Description of type?');
        $this->info('Type description is: ' . $this->type_args['description']);
        $model = new $name();


        $fields_done = false;
        $columns = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->select(DB::raw('COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT'))
            ->where('table_name', $model->getTable())->where('table_schema', env('DB_DATABASE'))->get();

        foreach ($columns as $field) {

            $field_arr = [
                'name' => $field->COLUMN_NAME,
                'is_null' => false,
                'type' => null
            ];
            if ($field->DATA_TYPE == 'int') {
                $field_arr['type'] = 'Int';
            } else {
                $field_arr['type'] = 'String';
            }
            if ($field->IS_NULLABLE == 'YES') {
                $field_arr['is_null'] = true;
            } else {
                $field_arr['is_null'] = false;
            }
            $this->type_args['fields'][] = $field_arr;
        }

        $field_array = [];
        foreach ($this->type_args['fields'] as $field) {
            $type = '';
            if (!$field['is_null']) {
                $type = '--Type::nonNull(Type::' . strtolower($field['type']) . '())--';
            } else {
                $type = '--Type::' . strtolower($field['type']) . '()--';
            }
            $field_array[$field['name']] = [
                'type' => $type
            ];
        }
        $this->type_args['fields'] = $field_array;
        $this->generateType();
    }

    /**
     * Generate defined module folders.
     */
    protected function generateType()
    {
        if (!$this->files->isDirectory('app/GraphQL')) {
            $this->files->makeDirectory('app/GraphQL');
            if (!$this->files->isDirectory('app/GraphQL/Type')) {
                $this->files->makeDirectory('app/GraphQL/Type');
            }
        }

        $pathMap = [];
        $directory = 'app/GraphQL/Type';
        $source = __DIR__ . '/stubs/Type.stub';

        $file = $this->files->get($source);

        if (!empty($pathMap)) {
            $search = array_keys($pathMap);
            $replace = array_values($pathMap);
        }

        $contents = $this->replacePlaceholdersType($file);

        $filePath = $directory;
        $dir = dirname($filePath);

        if (!$this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }
        $this->files->put($filePath . '/' . $this->type_args['className'] . '.php', $contents);


        $this->info("\nGraphQL type generated successfully.");
        $this->info("\n--------------------------------------");
        $this->info("\nIf you want to include relations in your type then you must add a new field in the array in the field method and for example: 'posts' => ['type' => Type::listOf(GraphQL::type('Post'))]");
        $this->info("\nAll columns in the database table is added in the field method, if you want to change or remove some fields that should not me querable then edit the newly created file.");
    }

    /**
     * Replaces placeholders in Mutation.stub
     *
     * @param $contents
     * @return mixed
     */
    protected function replacePlaceholdersMutation($contents)
    {


        $find = [
            'DummyNamespace',
            'DummyClass',
            'DummyName',
            'DummyDescription',
            'DummyType'
        ];

        $replace = [
            'App\GraphQL\Query',
            $this->type_args['className'],
            '"' . $this->type_args['name'] . '"',
            '"' . $this->type_args['description'] . '"',
            '"' . $this->type_args['graphQLType'] . '"'
        ];
        $array = str_replace("--'", '', str_replace("'--", '', rtrim(str_replace('array (', '[',
                str_replace("),", '],', var_export($this->type_args['args'], true))), ')'))) . ']';
        $str = '';
        foreach ($this->type_args['models'] as $m) {
            $str .= 'use ' . $m . ';' . PHP_EOL;
        }
        $contents = str_replace('DummyModels', $str, $contents);
        $content = str_replace($find, $replace, $contents);
        return str_replace('DummyArgs', $array, $content);
    }

    /**
     * Replaces placeholders in Query.stub
     *
     * @param $contents
     * @return mixed
     */
    protected function replacePlaceholdersQuery($contents)
    {
        $find = [
            'DummyNamespace',
            'DummyClass',
            'DummyName',
            'DummyDescription',
            'DummyType'
        ];

        $replace = [
            'App\GraphQL\Query',
            $this->type_args['className'],
            '"' . $this->type_args['name'] . '"',
            '"' . $this->type_args['description'] . '"',
            '"' . $this->type_args['graphQLType'] . '"'
        ];
        $array = str_replace("--'", '', str_replace("'--", '', rtrim(str_replace('array (', '[',
                str_replace("),", '],', var_export($this->type_args['args'], true))), ')'))) . ']';

        $content = str_replace($find, $replace, $contents);
        return str_replace('DummyArgs', $array, $content);
    }

    /**
     * Replaces placeaholders in Type.stub
     *
     * @param $contents
     * @return mixed
     */

    protected function replacePlaceholdersType($contents)
    {
        $find = [
            'DummyNamespace',
            'DummyClass',
            'DummyName',
            'DummyDescription'
        ];

        $replace = [
            'App\GraphQL\Type',
            $this->type_args['className'],
            '"' . $this->type_args['name'] . '"',
            '"' . $this->type_args['description'] . '"'
        ];
        $array = str_replace("--'", '', str_replace("'--", '', rtrim(str_replace('array (', '[',
                str_replace("),", '],', var_export($this->type_args['fields'], true))), ')'))) . ']';

        $content = str_replace($find, $replace, $contents);
        return str_replace('DummyFields', $array, $content);
    }
}