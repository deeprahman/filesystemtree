<?php

require __DIR__ .DIRECTORY_SEPARATOR. 'FileSystem.php'; // Include the FileSystem class definition

$fs = new FileSystem();

function printHelp() {
    echo "Commands:\n";
    echo "mkdir <dirname> - Create a directory\n";
    echo "rmdir <dirname> - Remove a directory\n";
    echo "ls [dirname] - List contents of a directory\n";
    echo "cd <dirname> - Change directory\n";
    echo "pwd - Print current working directory\n";
    echo "creat <filename> - Create a file\n";
    echo "rm <filename> - Remove a file\n";
    echo "reload <path> - Reload the file system from a path\n";
    echo "save <path> - Save the file system to a path\n";
    echo "menu - Show this menu\n";
    echo "quit - Exit the program\n";
}

// Infinite loop to continuously accept commands until "quit"
while (true) {
    echo "fs> ";  // Command-line prompt

    // Read user input
    $input = trim(fgets(STDIN));

    // Split input into command and arguments
    $parts = explode(' ', $input);
    $command = strtolower(array_shift($parts)); // Get the command
    $args = $parts;  // Remaining parts are arguments

    // Execute the corresponding method in FileSystem
    switch ($command) {
        case 'mkdir':
        case 'rmdir':
        case 'ls':
        case 'cd':
        case 'creat':
        case 'rm':
        case 'reload':
        case 'save':
            if (!isset($args[0])) {
                echo "Missing argument for $command\n";
                break;
            }
            $fs->executeCommand($command, $args);
            break;

        case 'pwd':
        case 'menu':
        case 'quit':
            $fs->executeCommand($command, []);
            if ($command === 'quit') {
                exit(0); // Exit the loop and terminate the program
            }
            break;

        case 'help':
            printHelp();
            break;

        default:
            echo "Unknown command: $command. Type 'menu' or 'help' for the list of commands.\n";
    }
}
