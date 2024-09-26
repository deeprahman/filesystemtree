<?php

declare(strict_types=1);

// Constants for node types
const DIR = 'DIR';
const REG = 'REG';

// Global variables
$root = null;
$cwd = null;

// Node structure
function create_node(string $name, string $type): array {
    return [
        'name' => $name,
        'type' => $type,
        'child' => null,
        'sibling' => null,
        'parent' => null
    ];
}

// Initialize filesystem
function init_filesystem(): void {
    global $root, $cwd;
    $root = create_node('', DIR);
    $cwd = $root;
}

/**
 * Search for a directory in the filesystem
 * 
 * Algorithm:
 * 1. If path is empty or ".", return current working directory
 * 2. If path is "/", return root
 * 3. Split path into parts
 * 4. Start from root or current directory based on whether path is absolute or relative
 * 5. For each part of the path:
 *    a. Search for the part in the current directory's children
 *    b. If found, move to that child; if not found, return null
 * 6. Return the final node found (or null if path doesn't exist)
 */
function search_dir(string $path): ?array {
    global $root, $cwd;
    if (empty($path) || $path === ".") return $cwd;
    if ($path === "/") return $root;

    $parts = array_filter(explode('/', $path), fn($p) => !empty($p));
    $current = $path[0] === '/' ? $root : $cwd;

    foreach ($parts as $part) {
        $current = search_in_dir($current, $part);
        if (!$current) return null;
    }

    return $current;
}

/**
 * Search for a node within a directory
 * 
 * Algorithm:
 * 1. Start with the first child of the directory
 * 2. While there are more children:
 *    a. If the current child's name matches the target, return it
 *    b. Move to the next sibling
 * 3. If no match is found, return null
 */
function search_in_dir(array $dir, string $name): ?array {
    $child = $dir['child'];
    while ($child) {
        if ($child['name'] === $name) return $child;
        $child = $child['sibling'];
    }
    return null;
}

/**
 * Insert a new node into the filesystem
 * 
 * Algorithm:
 * 1. If the parent has no children, make the new node the first child
 * 2. Otherwise, traverse to the last sibling and add the new node there
 * 3. Set the parent of the new node
 */
function insert_node(array &$parent, array &$new_node): void {
    if (!$parent['child']) {
        $parent['child'] = &$new_node;
    } else {
        $last_sibling = &$parent['child'];
        while ($last_sibling['sibling']) {
            $last_sibling = &$last_sibling['sibling'];
        }
        $last_sibling['sibling'] = &$new_node;
    }
    $new_node['parent'] = &$parent;
}

/**
 * Remove a node from the filesystem
 * 
 * Algorithm:
 * 1. If the node to remove is the first child, update the parent's child pointer
 * 2. Otherwise, traverse siblings to find the node and remove it from the chain
 * 3. Update parent pointers as necessary
 */
function remove_node(array &$parent, array &$node_to_remove): void {
    if ($parent['child'] === $node_to_remove) {
        $parent['child'] = $node_to_remove['sibling'];
    } else {
        $current = &$parent['child'];
        while ($current && $current['sibling'] !== $node_to_remove) {
            $current = &$current['sibling'];
        }
        if ($current) {
            $current['sibling'] = $node_to_remove['sibling'];
        }
    }
    if ($node_to_remove['sibling']) {
        $node_to_remove['sibling']['parent'] = &$parent;
    }
}

/**
 * Create a new directory
 * 
 * Algorithm:
 * 1. Parse the pathname to get dirname and basename
 * 2. Search for the parent directory
 * 3. If parent exists and is a directory, search for existing child with same name
 * 4. If no existing child, create new directory node and insert it
 */
function mkdir_fs(string $pathname): bool {
    if (empty($pathname)) {
        return log_error("Invalid pathname!");
    }

    ['dirname' => $dirname, 'basename' => $basename] = parse_path($pathname);
    $parent_dir = search_dir($dirname);

    if (!$parent_dir || $parent_dir['type'] !== DIR) {
        return log_error("Not a directory!");
    }

    if (search_in_dir($parent_dir, $basename)) {
        return log_error("Directory already exists!");
    }

    $new_directory = create_node($basename, DIR);
    insert_node($parent_dir, $new_directory);
    return true;
}

/**
 * Remove a directory
 * 
 * Algorithm:
 * 1. Parse the pathname to get dirname and basename
 * 2. Search for the parent directory
 * 3. Search for the directory to delete within the parent
 * 4. If directory exists and is empty, remove it
 */
function rmdir_fs(string $pathname): bool {
    if (empty($pathname)) {
        return log_error("Invalid pathname!");
    }

    ['dirname' => $dirname, 'basename' => $basename] = parse_path($pathname);
    $parent_dir = search_dir($dirname);

    if (!$parent_dir) {
        return log_error("Invalid pathname");
    }

    $dir_to_delete = search_in_dir($parent_dir, $basename);
    if (!$dir_to_delete || $dir_to_delete['child']) {
        return log_error("Directory not empty or not found!");
    }

    remove_node($parent_dir, $dir_to_delete);
    return true;
}

/**
 * Change current working directory
 * 
 * Algorithm:
 * 1. Search for the directory specified by pathname
 * 2. If found and it's a directory, update current working directory
 */
function cd(string $pathname): bool {
    global $cwd;
    $new_dir = search_dir($pathname);
    if (!$new_dir || $new_dir['type'] !== DIR) {
        return log_error("Invalid directory!");
    }
    $cwd = $new_dir;
    return true;
}

/**
 * List directory contents
 * 
 * Algorithm:
 * 1. Search for the directory specified by pathname
 * 2. If found and it's a directory, traverse its children and print their names and types
 */
function ls(string $pathname): bool {
    $dir = search_dir($pathname);
    if (!$dir || $dir['type'] !== DIR) {
        return log_error("Invalid directory!");
    }
    $current = $dir['child'];
    while ($current) {
        echo "{$current['name']}\t{$current['type']}\n";
        $current = $current['sibling'];
    }
    return true;
}

/**
 * Print working directory
 * 
 * Algorithm:
 * 1. Start from current working directory
 * 2. Use a stack to store node names as we traverse up to the root
 * 3. Pop names from the stack to build the path string
 */
function pwd(): bool {
    global $root, $cwd;
    $path = [];
    $current = $cwd;
    while ($current !== $root && $current !== null) {
        array_unshift($path, $current['name']);
        $current = $current['parent'];
    }
    echo '/' . implode('/', $path) . "\n";
    return true;
}

/**
 * Create a file
 * 
 * Algorithm:
 * 1. Parse the pathname to get dirname and basename
 * 2. Search for the parent directory
 * 3. If parent exists and is a directory, create new file node and insert it
 */
function creat(string $pathname): bool {
    if (empty($pathname)) {
        return log_error("Invalid pathname!");
    }

    ['dirname' => $dirname, 'basename' => $basename] = parse_path($pathname);
    $parent_dir = search_dir($dirname);

    if (!$parent_dir || $parent_dir['type'] !== DIR) {
        return log_error("Not a directory!");
    }

    $new_file = create_node($basename, REG);
    insert_node($parent_dir, $new_file);
    return true;
}

/**
 * Remove a file
 * 
 * Algorithm:
 * 1. Parse the pathname to get dirname and basename
 * 2. Search for the parent directory
 * 3. Search for the file to delete within the parent
 * 4. If file exists and is a regular file, remove it
 */
function rm(string $pathname): bool {
    if (empty($pathname)) {
        return log_error("Invalid pathname!");
    }

    ['dirname' => $dirname, 'basename' => $basename] = parse_path($pathname);
    $parent_dir = search_dir($dirname);

    if (!$parent_dir) {
        return log_error("Invalid pathname!");
    }

    $file_to_delete = search_in_dir($parent_dir, $basename);
    if (!$file_to_delete || $file_to_delete['type'] !== REG) {
        return log_error("File not found or not a regular file!");
    }

    remove_node($parent_dir, $file_to_delete);
    return true;
}

/**
 * Save filesystem to a file
 * 
 * Algorithm:
 * 1. Start from the root node
 * 2. Use a depth-first traversal (implemented with a stack) to visit all nodes
 * 3. For each node, write its type, path, and name to the file
 */
function save(string $filepath): bool {
    global $root;
    if (!$root) {
        echo "Uninitialized Filesystem!\n";
        return false;
    }

    $stack = [[$root, '']];
    $content = '';

    while (!empty($stack)) {
        [$node, $path] = array_pop($stack);
        $content .= "{$node['type']}\t{$path}/{$node['name']}\n";

        if ($node['child']) {
            $stack[] = [$node['child'], $path . '/' . $node['name']];
        }
        if ($node['sibling']) {
            $stack[] = [$node['sibling'], $path];
        }
    }

    file_put_contents($filepath, $content);
    return true;
}

/**
 * Reload filesystem from a file
 * 
 * Algorithm:
 * 1. Read the file line by line
 * 2. For each line, parse the type and path
 * 3. Recreate the filesystem structure by calling mkdir_fs or creat for each node
 */
function reload(string $filepath): bool {
    if (!file_exists($filepath)) {
        echo "No saved filesystem!\n";
        return false;
    }

    init_filesystem(); // Reset the filesystem

    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        [$type, $path] = explode("\t", trim($line));
        if ($type === DIR) {
            mkdir_fs($path);
        } else {
            creat($path);
        }
    }
    return true;
}

function menu(): bool {
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
    foreach ($commands as $cmd) {
        echo "$cmd\n";
    }
    return true;
}

function quit(): bool {
    save('FileSystemDefault.txt');
    echo "File system saved and program terminated.\n";
    exit(0);
}

function log_error(string $error): bool {
    echo "Error: $error\n";
    return false;
}

function parse_path(string $fullPath): array {
    $parts = array_filter(explode('/', $fullPath), fn($p) => !empty($p));
    $basename = array_pop($parts) ?? '';
    $dirname = '/' . implode('/', $parts);
    return ['dirname' => $dirname, 'basename' => $basename];
}

// Initialize the filesystem
init_filesystem();

// Show initial menu
menu();

// Main loop
while (true) {
    $input = trim(fgets(STDIN));
    $parts = explode(' ', $input);
    $command = $parts[0];
    $args = array_slice($parts, 1);

    switch ($command) {
        case 'mkdir':
            mkdir_fs($args[0] ?? '');
            break;
        case 'rmdir':
            rmdir_fs($args[0] ?? '');
            break;
        case 'cd':
            cd($args[0] ?? '/');
            break;
        case 'ls':
            ls($args[0] ?? '');
            break;
        case 'pwd':
            pwd();
            break;
        case 'creat':
            creat($args[0] ?? '');
            break;
        case 'rm':
            rm($args[0] ?? '');
            break;
        case 'save':
            save($args[0] ?? 'FileSystemDefault.txt');
            break;
        case 'reload':
            reload($args[0] ?? 'FileSystemDefault.txt');
            break;
        case 'menu':
            menu();
            break;
        case 'quit':
            quit();
            break;
        default:
            echo "Invalid command. Use 'menu' to see available commands.\n";
    }
}