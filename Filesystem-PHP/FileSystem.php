<?php
require_once(__DIR__ . "/NodeType.php");
require_once(__DIR__ . "/FileSystemNode.php");

class FileSystem {
    public FileSystemNode $root;
    public FileSystemNode $cwd;

    public function __construct() {
        // Root directory with empty name
        $this->root = new FileSystemNode('', NodeType::DIR);
        $this->cwd = $this->root;  // Current working directory starts at root
    }

    public function findCmd(string $command): int {
        $commands = ['mkdir', 'rmdir', 'ls', 'cd', 'pwd', 'creat', 'rm', 'reload', 'save', 'menu', 'quit'];
        return array_search($command, $commands, true);
    }

    public function executeCommand(string $command, array $args): bool {
        $cmdIndex = $this->findCmd($command);
        switch ($cmdIndex) {
            case 0: return $this->mkdir($args[0]);
            case 1: return $this->rmdir($args[0]);
            case 2: return $this->ls($args[0]);
            case 3: return $this->cd($args[0]);
            case 4: return $this->pwd();
            case 5: return $this->creat($args[0]);
            case 6: return $this->rm($args[0]);
            case 7: return $this->reload($args[0]);
            case 8: return $this->save($args[0]);
            case 9: return $this->menu();
            case 10: return $this->quit();
            default:
                echo "Invalid command\n";
                return false;
        }
    }

    public function mkdir(string $pathName): bool {
        if (empty($pathName)) {
            $this->logError("Invalid pathname!");
            return false;
        }
        list($dirname, $basename) = $this->dbname($pathName);
        $parentDir = $this->searchDir($dirname);
        if (!$parentDir || $parentDir->type !== NodeType::DIR) {
            $this->logError("Not a directory!");
            return false;
        }

        // Check if directory or file already exists
        if ($this->searchDir($pathName) !== null) {
            $this->logError("Directory already exists!");
            return false;
        }

        // Create new directory node and link it to the parent directory
        $newDir = new FileSystemNode($basename, NodeType::DIR);
        $newDir->parentPtr = $parentDir;
        if ($parentDir->childPtr === null) {
            $parentDir->childPtr = $newDir;
        } else {
            $sibling = $parentDir->childPtr;
            while ($sibling->siblingPtr !== null) {
                $sibling = $sibling->siblingPtr;
            }
            $sibling->siblingPtr = $newDir;
        }
        return true;
    }

    public function rmdir(string $path): bool {
        $dir = $this->searchDir($path);
        if ($dir === null || $dir->type !== NodeType::DIR) {
            $this->logError("Directory does not exist!");
            return false;
        }

        // Ensure directory is empty
        if ($dir->childPtr !== null) {
            $this->logError("Directory is not empty!");
            return false;
        }

        // Remove directory from its parent's child/sibling pointers
        if ($dir->parentPtr->childPtr === $dir) {
            $dir->parentPtr->childPtr = $dir->siblingPtr;
        } else {
            $sibling = $dir->parentPtr->childPtr;
            while ($sibling->siblingPtr !== $dir) {
                $sibling = $sibling->siblingPtr;
            }
            $sibling->siblingPtr = $dir->siblingPtr;
        }
        return true;
    }

    public function ls(string $path = ''): bool {
        $dir = empty($path) ? $this->cwd : $this->searchDir($path);
        if ($dir === null || $dir->type !== NodeType::DIR) {
            $this->logError("Not a directory!");
            return false;
        }

        // List contents
        $child = $dir->childPtr;
        while ($child !== null) {
            echo $child->name . "\n";
            $child = $child->siblingPtr;
        }
        return true;
    }

    public function cd(string $path): bool {
        $dir = $this->searchDir($path);
        if ($dir === null || $dir->type !== NodeType::DIR) {
            $this->logError("Directory does not exist!");
            return false;
        }

        $this->cwd = $dir;
        return true;
    }

    public function pwd(): bool {
        $path = '';
        $node = $this->cwd;
        while ($node !== $this->root) {
            $path = '/' . $node->name . $path;
            $node = $node->parentPtr;
        }
        echo $path === '' ? '/' : $path;
        echo "\n";
        return true;
    }

    public function creat(string $path): bool {
        list($dirname, $basename) = $this->dbname($path);
        $parentDir = $this->searchDir($dirname);
        if ($parentDir === null || $parentDir->type !== NodeType::DIR) {
            $this->logError("Not a directory!");
            return false;
        }

        // Check if file already exists
        if ($this->searchDir($path) !== null) {
            $this->logError("File already exists!");
            return false;
        }

        // Create file node
        $newFile = new FileSystemNode($basename, NodeType::REG);
        $newFile->parentPtr = $parentDir;
        if ($parentDir->childPtr === null) {
            $parentDir->childPtr = $newFile;
        } else {
            $sibling = $parentDir->childPtr;
            while ($sibling->siblingPtr !== null) {
                $sibling = $sibling->siblingPtr;
            }
            $sibling->siblingPtr = $newFile;
        }
        return true;
    }

    public function rm(string $path): bool {
        $file = $this->searchDir($path);
        if ($file === null || $file->type !== NodeType::REG) {
            $this->logError("File does not exist!");
            return false;
        }

        // Remove file from its parent's child/sibling pointers
        if ($file->parentPtr->childPtr === $file) {
            $file->parentPtr->childPtr = $file->siblingPtr;
        } else {
            $sibling = $file->parentPtr->childPtr;
            while ($sibling->siblingPtr !== $file) {
                $sibling = $sibling->siblingPtr;
            }
            $sibling->siblingPtr = $file->siblingPtr;
        }
        return true;
    }

    public function reload(string $path): bool {
        // Check if the file exists
        if (!file_exists($path)) {
            echo "File not found: $path\n";
            return false;
        }
    
        // Read the saved file and unserialize the data
        try {
            $serializedData = file_get_contents($path);
            $this->root = unserialize($serializedData);
            $this->cwd = $this->root;  // Reset current working directory to root
            echo "File system reloaded from $path\n";
            return true;
        } catch (Exception $e) {
            echo "Failed to reload file system: " . $e->getMessage() . "\n";
            return false;
        }
    }
    

    public function save(string $path): bool {
        // Serialize the file system structure (root node)
        $serializedData = serialize($this->root);
    
        // Write serialized data to the file at the specified path
        try {
            file_put_contents($path, $serializedData);
            echo "File system saved to $path\n";
            return true;
        } catch (Exception $e) {
            echo "Failed to save file system: " . $e->getMessage() . "\n";
            return false;
        }
    }
    

    public function menu(): bool {
        echo "Available commands: mkdir, rmdir, ls, cd, pwd, creat, rm, reload, save, menu, quit\n";
        return true;
    }

    public function quit(): bool {
        echo "Exiting file system simulation\n";
        return true;
    }

    // Utility functions
    private function dbname(string $pathName): array {
        $dirname = dirname($pathName);
        $basename = basename($pathName);
        return [$dirname, $basename];
    }

    private function searchDir(string $dirname): ?FileSystemNode {
        // Traverse the file system to find the directory or file by path
        $current = $this->cwd;
        $parts = explode('/', trim($dirname, '/'));

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            $found = false;
            $child = $current->childPtr;

            while ($child !== null) {
                if ($child->name === $part) {
                    $current = $child;
                    $found = true;
                    break;
                }
                $child = $child->siblingPtr;
            }

            if (!$found) {
                return null;
            }
        }

        return $current;
    }

    private function logError(string $message): void {
        echo $message . "\n";
    }
}
