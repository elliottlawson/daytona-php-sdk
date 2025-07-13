<?php

namespace ElliottLawson\Daytona\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ElliottLawson\Daytona\Sandbox createSandbox(\ElliottLawson\Daytona\DTOs\SandboxCreateParameters $params, bool $waitForStart = true)
 * @method static void deleteSandbox(string $sandboxId)
 * @method static \ElliottLawson\Daytona\DTOs\SandboxResponse getSandbox(string $sandboxId)
 * @method static \ElliottLawson\Daytona\Sandbox getSandboxById(string $sandboxId)
 * @method static void startSandbox(string $sandboxId)
 * @method static void stopSandbox(string $sandboxId)
 * @method static \ElliottLawson\Daytona\DTOs\CommandResponse executeCommand(string $sandboxId, string $command, ?string $cwd = null, ?array $env = null, ?int $timeout = null)
 * @method static string readFile(string $sandboxId, string $path)
 * @method static void writeFile(string $sandboxId, string $path, string $content)
 * @method static \ElliottLawson\Daytona\DTOs\DirectoryListingResponse listDirectory(string $sandboxId, string $path)
 * @method static void deleteFile(string $sandboxId, string $path)
 * @method static bool fileExists(string $sandboxId, string $path)
 * @method static void gitClone(string $sandboxId, string $url, ?string $branch = null, string $path = '/workspace', ?string $username = null, ?string $password = null)
 * @method static \ElliottLawson\Daytona\DTOs\GitBranchesResponse gitListBranches(string $sandboxId, string $repoPath = '/workspace')
 * @method static void gitAdd(string $sandboxId, string $repoPath, array $filePaths)
 * @method static void gitCommit(string $sandboxId, string $repoPath, string $message, string $authorName, string $authorEmail)
 * @method static void gitPush(string $sandboxId, string $repoPath, ?string $username = null, ?string $password = null)
 * @method static \ElliottLawson\Daytona\DTOs\GitStatusResponse gitStatus(string $sandboxId, string $repoPath = '/workspace')
 * @method static \ElliottLawson\Daytona\DTOs\GitHistoryResponse gitHistory(string $sandboxId, string $repoPath = '/workspace')
 * @method static \ElliottLawson\Daytona\Sandbox sandboxFromResponse(\ElliottLawson\Daytona\DTOs\SandboxResponse $response)
 *
 * @see \ElliottLawson\Daytona\DaytonaClient
 */
class Daytona extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'daytona';
    }
}
