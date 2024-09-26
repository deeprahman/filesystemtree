<?php

declare(strict_types=1);

enum NodeType: string {
    case DIR = 'DIR';
    case REG = 'REG';
}

class FileSystemNode {
    public ?FileSystemNode $childPtr = null;
    public ?FileSystemNode $siblingPtr = null;
    public ?FileSystemNode $parentPtr = null;

    public function __construct(
        public string $name,
        public NodeType $type
    ) {}
}

class FileSystem {
    private FileSystemNode $root;
    private FileSystemNode $cwd;

    public function __construct() {
        $this->root = new FileSystemNode('', NodeType::DIR);
        $this->cwd = $this->root;
    }

    public function executeCommand(string $command, array $args): bool {
        $commands = [
            'mkdir' => fn($args) => $this->mkdir($args[0] ?? ''),
            'rmdir' => fn($args) => $this->rmdir($args[0] ?? ''),
            'ls' => fn($args) => $this->ls($args[0] ?? ''),
            'cd' => fn($args) => $this->cd($args[0] ?? ''),
            'pwd' => fn($args) => $this->pwd(),
            'creat' => fn($args) => $this->creat($args[0] ?? ''),
            'rm' => fn($args) => $this->rm($args[0] ?? ''),
            'reload' => fn($args) => $this->reload($args[0] ?? ''),
            'save' => fn($args) => $this->save($args[0] ?? ''),
            'menu' => fn($args) => $this->menu(),
            'quit' => fn($args) => $this->quit(),
        ];

        return $commands[$command]($args) ?? false;
    }

    private function mkdir(string $pathName): bool {
        if (empty($pathName)) {
            return $this->logError("Invalid pathname!");
        }

        ['dirname' => $dirname, 'basename' => $basename] = $this->dbname($pathName);
        $parentDir = $this->searchDir($dirname);

        if (!$parentDir || $parentDir->type !== NodeType::DIR) {
            return $this->logError("Not a directory!");
        }

        if ($this->searchInDir($parentDir, $basename)) {
            return $this->logError("Directory already exists!");
        }

        $newDirectory = new FileSystemNode($basename, NodeType::DIR);
        $this->insertNode($parentDir, $newDirectory);
        return true;
    }

    private function rmdir(string $pathName): bool {
        if (empty($pathName)) {
            return $this->logError("Invalid pathname!");
        }

        ['dirname' => $dirname, 'basename' => $basename] = $this->dbname($pathName);
        $parentDir = $this->searchDir($dirname);

        if (!$parentDir) {
            return $this->logError("Invalid pathname");
        }

        $dirToDelete = $this->searchInDir($parentDir, $basename);
        if (!$dirToDelete || $dirToDelete->childPtr) {
            return $this->logError("Directory not empty or not found!");
        }

        $this->removeNode($parentDir, $dirToDelete);
        return true;
    }

    private function cd(string $pathName): bool {
        $newDir = $this->searchDir($pathName);
        if (!$newDir || $newDir->type !== NodeType::DIR) {
            return $this->logError("Invalid directory!");
        }
        $this->cwd = $newDir;
        return true;
    }

    private function ls(string $pathName): bool {
        $dir = $this->searchDir($pathName);
        if (!$dir || $dir->type !== NodeType::DIR) {
            return $this->logError("Invalid directory!");
        }
        $current = $dir->childPtr;
        while ($current) {
            echo "{$current->name}\t{$current->type->value}\n";
            $current = $current->siblingPtr;
        }
        return true;
    }

    private function pwd(): bool {
        $path = $this->getPath($this->cwd);
        echo '/' . implode('/', $path) . "\n";
        return true;
    }

    private function creat(string $pathName): bool {
        if (empty($pathName)) {
            return $this->logError("Invalid pathname!");
        }

        ['dirname' => $dirname, 'basename' => $basename] = $this->dbname($pathName);
        $parentDir = $this->searchDir($dirname);

        if (!$parentDir || $parentDir->type !== NodeType::DIR) {
            return $this->logError("Not a directory!");
        }

        $newFile = new FileSystemNode($basename, NodeType::REG);
        $this->insertNode($parentDir, $newFile);
        return true;
    }

    private function rm(string $pathName): bool {
        if (empty($pathName)) {
            return $this->logError("Invalid pathname!");
        }

        ['dirname' => $dirname, 'basename' => $basename] = $this->dbname($pathName);
        $parentDir = $this->searchDir($dirname);

        if (!$parentDir) {
            return $this->logError("Invalid pathname!");
        }

        $fileToDelete = $this->searchInDir($parentDir, $basename);
        if (!$fileToDelete || $fileToDelete->type !== NodeType::REG) {
            return $this->logError("File not found or not a regular file!");
        }

        $this->removeNode($parentDir, $fileToDelete);
        return true;
    }

    private function save(string $filePath): bool {
        if (!$this->root) {
            echo "Uninitialized Filesystem!\n";
            return false;
        }
        $content = $this->serialize($this->root, '');
        file_put_contents($filePath, $content);
        return true;
    }

    private function serialize(?FileSystemNode $node, string $prefix): string {
        if (!$node) return '';
        $result = "{$node->type->value}\t{$prefix}/{$node->name}\n";
        if ($node->childPtr) $result .= $this->serialize($node->childPtr, $prefix . '/' . $node->name);
        if ($node->siblingPtr) $result .= $this->serialize($node->siblingPtr, $prefix);
        return $result;
    }

    private function reload(string $filePath): bool {
        if (!file_exists($filePath)) {
            echo "No saved filesystem!\n";
            return false;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        array_map(function($line) {
            [$type, $path] = explode("\t", trim($line));
            if ($type === NodeType::DIR->value) {
                $this->mkdir($path);
            } else {
                $this->creat($path);
            }
        }, $lines);
        return true;
    }

    private function menu(): bool {
        $commands = [
            "mkdir pathname : make a new directory for a given pathname",
            "rmdir pathname : remove the directory, if it is empty",
            "cd [pathname]  : change CWD to pathname, or to / if no pathname",
            "ls [pathname]  : list the directory contents of pathname or CWD",
            "pwd            : print the (absolute) pathname of CWD",
            "creat pathname : create a FILE node",
            "rm pathname    : remove the FILE node",
            "save filename  : save the current file system tree as a file",
            "reload filename: construct a file system tree from a file",
            "menu           : show a menu of valid commands",
            "quit           : save the file system tree, then terminate the program"
        ];

        echo "Command List\n";
        echo "------------\n";
        array_map(fn($cmd) => echo "$cmd\n", $commands);
        return true;
    }

    private function quit(): bool {
        $this->save('FileSystemDefault.txt');
        echo "File system saved and program terminated.\n";
        exit(0);
    }

    private function logError(string $error): bool {
        echo "Error: $error\n";
        return false;
    }

    private function dbname(string $fullPath): array {
        $parts = array_filter(explode('/', $fullPath), fn($p) => strlen($p) > 0);
        $basename = array_pop($parts) ?? '';
        $dirname = '/' . implode('/', $parts);
        return ['dirname' => $dirname, 'basename' => $basename];
    }

    private function searchDir(string $dirPath): ?FileSystemNode {
        if (empty($dirPath) || $dirPath === ".") return $this->cwd;
        if ($dirPath === "/") return $this->root;
        $parts = array_filter(explode('/', $dirPath), fn($p) => strlen($p) > 0);
        return array_reduce($parts, fn($current, $part) => 
            $current ? $this->searchInDir($current, $part) : null, 
            $this->root
        );
    }

    private function searchInDir(FileSystemNode $dirNode, string $fileName): ?FileSystemNode {
        $child = $dirNode->childPtr;
        while ($child) {
            if ($child->name === $fileName) return $child;
            $child = $child->siblingPtr;
        }
        return null;
    }

    private function insertNode(FileSystemNode $parentNode, FileSystemNode $newNode): void {
        if (!$parentNode->childPtr) {
            $parentNode->childPtr = $newNode;
        } else {
            $lastSibling = $parentNode->childPtr;
            while ($lastSibling->siblingPtr) {
                $lastSibling = $lastSibling->siblingPtr;
            }
            $lastSibling->siblingPtr = $newNode;
        }
        $newNode->parentPtr = $parentNode;
    }

    private function removeNode(FileSystemNode $parentNode, FileSystemNode $nodeToRemove): void {
        if ($parentNode->childPtr === $nodeToRemove) {
            $parentNode->childPtr = $nodeToRemove->siblingPtr;
        } else {
            $current = $parentNode->childPtr;
            while ($current && $current->siblingPtr !== $nodeToRemove) {
                $current = $current->siblingPtr;
            }
            if ($current) {
                $current->siblingPtr = $nodeToRemove->siblingPtr;
            }
        }
        if ($nodeToRemove->siblingPtr) {
            $nodeToRemove->siblingPtr->parentPtr = $parentNode;
        }
    }

    private function getPath(FileSystemNode $node): array {
        $path = [];
        $current = $node;
        while ($current !== $this->root && $current !== null) {
            array_unshift($path, $current->name);
            $current = $current->parentPtr;
        }
        return $path;
    }
}

$fileSystem = new FileSystem();
$fileSystem->menu();  // Show menu of commands

// Main loop
while (true) {
    $input = trim(fgets(STDIN));
    $parts = explode(' ', $input);
    $command = $parts[0];
    $args = array_slice($parts, 1);

    if ($command === 'quit') {
        $fileSystem->executeCommand($command, $args);
        break;
    }

    $fileSystem->executeCommand($command, $args);
}