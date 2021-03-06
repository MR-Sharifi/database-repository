<?php

namespace Changiz\DatabaseRepository\Commands;

use Changiz\DatabaseRepository\CustomMySqlQueries;
use Illuminate\Console\Command;

class MakeRedisRepository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:make-redis-repository {table_name} {--k|foreign-keys : Detect foreign keys} {--d|delete : Delete resource} {--f|force : Override/Delete existing redis repository}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Redis repository class';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    use CustomMySqlQueries;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $tableName = $this->argument('table_name');
        $detectForeignKeys = $this->option('foreign-keys');
        $entityName = str_singular(ucfirst(camel_case($tableName)));
        $entityVariableName = camel_case($entityName);
        $factoryName = $entityName . "Factory";
        $interfaceName = "I$entityName" . "Repository";
        $redisRepositoryName = "Redis$entityName" . "Repository";
        $relativeRedisRepositoryPath = config('repository.path.relative.repository') . "\\$entityName";

        if ($this->option('delete')) {
            unlink("$relativeRedisRepositoryPath/$redisRepositoryName.php");
            $this->info("Redis Repository \"$redisRepositoryName\" has been deleted.");
            return 0;
        }

        if (!file_exists($relativeRedisRepositoryPath)) {
            mkdir($relativeRedisRepositoryPath);
        }

        if (class_exists("$relativeRedisRepositoryPath\\$redisRepositoryName") && !$this->option('force')) {
            $this->alert("Repository $redisRepositoryName is already exist!");
            die;
        }

        $columns = $this->getAllColumnsInTable($tableName);

        if ($columns->isEmpty()) {
            $this->alert("Couldn't retrieve columns from table " . $tableName . "! Perhaps table's name is misspelled.");
            die;
        }

        if ($detectForeignKeys) {
            $foreignKeys = $this->extractForeignKeys($tableName);
        }

        // Initialize Redis Repository
        $redisRepositoryContent = "<?php\n\nnamespace $relativeRedisRepositoryPath;\n\n";
        $redisRepositoryContent .= "use App\Models\Repositories\RedisRepository;\n\n";
        $redisRepositoryContent .= "class $redisRepositoryName extends RedisRepository\n{";
        $redisRepositoryContent .= "}";

        file_put_contents("$relativeRedisRepositoryPath/$redisRepositoryName.php", $redisRepositoryContent);

        shell_exec("git add $relativeRedisRepositoryPath/$redisRepositoryName.php");

        $this->info("Redis Repository \"$redisRepositoryName\" has been created.");

        return 0;
    }
}
