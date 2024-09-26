<?php

declare(strict_types=1);

// Constants for node types
const NODE_TYPE_DIR = 'DIR';
const NODE_TYPE_REG = 'REG';

// Global variables
$root = create_node('', NODE_TYPE_DIR);
$cwd = $root;

// Main function to run the filesystem
function run_filesystem() {
    global $root, $cwd;
    
    show_menu();
    
    while (true) {
        $input = trim(fgets(STDIN));
        $parts = explode(' ', $input);
        $command = $parts[0];
        $args = array_slice($parts, 1);
        
        if ($command === 'quit') {
            save('FileSystemDefault.txt');
            echo "File system saved and program terminated.\n";
            break;
        }
        
        execute_command($command, $args);
    }
}

// Function to create a new node
function create_node(string $name, string $type): array {
    return [
        'name' => $name,
        'type' => $type,
        'children' => [],
        'parent' => null
    ];
}

// Function to execute commands
function execute_command(string $command, array $args): void {
    $commands = [
        'mkdir' => 'make_directory',
        'rmdir' => 'remove_directory',
        'ls' => 'list_directory',
        'cd' => 'change_directory',
        'pwd' => 'print_working_directory',
        'creat' => 'create_file',
        'rm' => 'remove_file',
        'reload' => 'reload_filesystem',
        'save' => 'save_filesystem',
        'menu' => 'show_menu'
    ];
    
    if (isset($commands[$command])) {
        $commands[$command]($args[0] ?? '');
    } else {
        echo "Invalid command\n";
    }
}

// Function to make a new directory
function make_directory(string $path_name): void {
    global $cwd;
    if (empty($path_name)) {
        echo "Error: Invalid pathname!\n";
        return;
    }
    
    $parent_dir = find_node($path_name, true);
    if (!$parent_dir) {
        echo "Error: Invalid pathname!\n";
        return;
    }
    
    $basename = basename($path_name);
    if (find_child($parent_dir, $basename)) {
        echo "Error: Directory already exists!\n";
        return;
    }
    
    $new_dir = create_node($basename, NODE_TYPE_DIR);
    $new_dir['parent'] = $parent_dir;
    $parent_dir['children'][] = $new_dir;
    echo "Directory created successfully.\n";
}

// Function to remove a directory
function remove_directory(string $path_name): void {
    if (empty($path_name)) {
        echo "Error: Invalid pathname!\n";
        return;
    }
    
    $node = find_node($path_name);
    if (!$node || $node['type'] !== NODE_TYPE_DIR) {
        echo "Error: Directory not found!\n";
        return;
    }
    
    if (!empty($node['children'])) {
        echo "Error: Directory not empty!\n";
        return;
    }
    
    $parent = $node['parent'];
    $parent['children'] = array_filter($parent['children'], fn($child) => $child !== $node);
    echo "Directory removed successfully.\n";
}

// Function to list directory contents
function list_directory(string $path_name): void {
    global $cwd;
    $dir = empty($path_name) ? $cwd : find_node($path_name);
    
    if (!$dir || $dir['type'] !== NODE_TYPE_DIR) {
        echo "Error: Invalid directory!\n";
        return;
    }
    
    foreach ($dir['children'] as $child) {
        echo "{$child['name']}\t{$child['type']}\n";
    }
}

// Function to change current working directory
function change_directory(string $path_name): void {
    global $root, $cwd;
    
    if (empty($path_name)) {
        $cwd = $root;
        echo "Changed to root directory.\n";
        return;
    }
    
    $new_dir = find_node($path_name);
    if (!$new_dir || $new_dir['type'] !== NODE_TYPE_DIR) {
        echo "Error: Invalid directory!\n";
        return;
    }
    
    $cwd = $new_dir;
    echo "Directory changed successfully.\n";
}

// Function to print current working directory
function print_working_directory(): void {
    global $cwd, $root;
    $path = [];
    $current = $cwd;
    
    while ($current !== $root) {
        array_unshift($path, $current['name']);
        $current = $current['parent'];
    }
    
    echo '/' . implode('/', $path) . "\n";
}

// Function to create a file
function create_file(string $path_name): void {
    if (empty($path_name)) {
        echo "Error: Invalid pathname!\n";
        return;
    }
    
    $parent_dir = find_node(dirname($path_name), true);
    if (!$parent_dir) {
        echo "Error: Invalid pathname!\n";
        return;
    }
    
    $basename = basename($path_name);
    if (find_child($parent_dir, $basename)) {
        echo "Error: File already exists!\n";
        return;
    }
    
    $new_file = create_node($basename, NODE_TYPE_REG);
    $new_file['parent'] = $parent_dir;
    $parent_dir['children'][] = $new_file;
    echo "File created successfully.\n";
}

// Function to remove a file
function remove_file(string $path_name): void {
    if (empty($path_name)) {
        echo "Error: Invalid pathname!\n";
        return;
    }
    
    $node = find_node($path_name);
    if (!$node || $node['type'] !== NODE_TYPE_REG) {
        echo "Error: File not found!\n";
        return;
    }
    
    $parent = $node['parent'];
    $parent['children'] = array_filter($parent['children'], fn($child) => $child !== $node);
    echo "File removed successfully.\n";
}

// Function to save the filesystem
function save_filesystem(string $file_path): void {
    global $root;
    $content = serialize_tree($root);
    file_put_contents($file_path, $content);
    echo "Filesystem saved successfully.\n";
}

// Function to reload the filesystem
function reload_filesystem(string $file_path): void {
    global $root, $cwd;
    if (!file_exists($file_path)) {
        echo "Error: No saved filesystem!\n";
        return;
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $root = create_node('', NODE_TYPE_DIR);
    $cwd = $root;
    
    foreach ($lines as $line) {
        [$type, $path] = explode("\t", $line);
        if ($type === NODE_TYPE_DIR) {
            make_directory($path);
        } else {
            create_file($path);
        }
    }
    
    echo "Filesystem reloaded successfully.\n";
}

// Function to show menu
function show_menu(): void {
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
}

// Function to find a node in the filesystem tree
function find_node(string $path, bool $parent = false): ?array {
    global $root, $cwd;
    
    if ($path === '/') return $root;
    if ($path === '.') return $cwd;
    
    $parts = explode('/', $path);
    $current = $path[0] === '/' ? $root : $cwd;
    
    $stack = new SplStack();
    foreach (array_reverse($parts) as $part) {
        if ($part !== '') {
            $stack->push($part);
        }
    }
    
    while (!$stack->isEmpty()) {
        $part = $stack->pop();
        if ($parent && $stack->isEmpty()) return $current;
        
        $found = false;
        foreach ($current['children'] as $child) {
            if ($child['name'] === $part) {
                $current = $child;
                $found = true;
                break;
            }
        }
        
        if (!$found) return null;
    }
    
    return $current;
}

// Function to find a child node by name
function find_child(array $parent, string $name): ?array {
    foreach ($parent['children'] as $child) {
        if ($child['name'] === $name) {
            return $child;
        }
    }
    return null;
}

// Function to serialize the filesystem tree
function serialize_tree(array $node, string $prefix = ''): string {
    $result = "{$node['type']}\t{$prefix}/{$node['name']}\n";
    foreach ($node['children'] as $child) {
        $result .= serialize_tree($child, $prefix . '/' . $node['name']);
    }
    return $result;
}

// Run the filesystem
run_filesystem();