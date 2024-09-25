import * as fs from 'fs';
import * as path from 'path';

enum NodeType {
    DIR = 'DIR',
    REG = 'REG'
}

class FileSystemNode {
    name: string;
    type: NodeType;
    childPtr: FileSystemNode | null = null;
    siblingPtr: FileSystemNode | null = null;
    parentPtr: FileSystemNode | null = null;

    constructor(name: string, type: NodeType) {
        this.name = name;
        this.type = type;
    }
}

class FileSystem {
    root: FileSystemNode;
    cwd: FileSystemNode;

    constructor() {
        this.root = new FileSystemNode('', NodeType.DIR);  // root directory with empty name
        this.cwd = this.root;  // current working directory starts at root
    }

    findCmd(command: string): number {
        const commands = ['mkdir', 'rmdir', 'ls', 'cd', 'pwd', 'creat', 'rm', 'reload', 'save', 'menu', 'quit'];
        return commands.indexOf(command);
    }

    executeCommand(command: string, args: string[]): boolean {
        const cmdIndex = this.findCmd(command);
        switch (cmdIndex) {
            case 0: return this.mkdir(args[0]);
            case 1: return this.rmdir(args[0]);
            case 2: return this.ls(args[0]);
            case 3: return this.cd(args[0]);
            case 4: return this.pwd();
            case 5: return this.creat(args[0]);
            case 6: return this.rm(args[0]);
            case 7: return this.reload(args[0]);
            case 8: return this.save(args[0]);
            case 9: return this.menu();
            case 10: return this.quit();
            default:
                console.log('Invalid command');
                return false;
        }
    }

    mkdir(pathName: string): boolean {
        if (!pathName) {
            this.logError("Invalid pathname!");
            return false;
        }
        let { dirname, basename } = this.dbname(pathName);
        let parentDir = this.searchDir(dirname);
        if (!parentDir || parentDir.type !== NodeType.DIR) {
            this.logError("Not a directory!");
            return false;
        }
        let existing = this.searchInDir(parentDir, basename);
        if (existing) {
            this.logError("Directory already exists!");
            return false;
        }
        let newDirectory = new FileSystemNode(basename, NodeType.DIR);
        this.insertNode(parentDir, newDirectory);
        return true;
    }

    rmdir(pathName: string): boolean {
        if (!pathName) {
            this.logError("Invalid pathname!");
            return false;
        }
        let { dirname, basename } = this.dbname(pathName);
        let parentDir = this.searchDir(dirname);
        if (!parentDir) {
            this.logError("Invalid pathname");
            return false;
        }
        let dirToDelete = this.searchInDir(parentDir, basename);
        if (!dirToDelete || dirToDelete.childPtr) {
            this.logError("Directory not empty or not found!");
            return false;
        }
        this.removeNode(parentDir, dirToDelete);
        return true;
    }

    cd(pathName: string): boolean {
        let newDir = this.searchDir(pathName);
        if (!newDir || newDir.type !== NodeType.DIR) {
            this.logError("Invalid directory!");
            return false;
        }
        this.cwd = newDir;
        return true;
    }

    ls(pathName: string): boolean {
        let dir = this.searchDir(pathName);
        if (!dir || dir.type !== NodeType.DIR) {
            this.logError("Invalid directory!");
            return false;
        }
        let current = dir.childPtr;
        while (current) {
            console.log(`${current.name}\t${current.type}`);
            current = current.siblingPtr;
        }
        return true;
    }

    pwd(): boolean {
        let current: FileSystemNode | null = this.cwd;
        let path = [];
        while (current !== this.root && current != null) {
            path.unshift(current.name);
            current = current.parentPtr;
        }
        console.log('/' + path.join('/'));
        return true;
    }

    creat(pathName: string): boolean {
        if (!pathName) {
            this.logError("Invalid pathname!");
            return false;
        }
        let { dirname, basename } = this.dbname(pathName);
        let parentDir = this.searchDir(dirname);
        if (!parentDir || parentDir.type !== NodeType.DIR) {
            this.logError("Not a directory!");
            return false;
        }
        let newFile = new FileSystemNode(basename, NodeType.REG);
        this.insertNode(parentDir, newFile);
        return true;
    }

    rm(pathName: string): boolean {
        if (!pathName) {
            this.logError("Invalid pathname!");
            return false;
        }
        let { dirname, basename } = this.dbname(pathName);
        let parentDir = this.searchDir(dirname);
        if (!parentDir) {
            this.logError("Invalid pathname!");
            return false;
        }
        let fileToDelete = this.searchInDir(parentDir, basename);
        if (!fileToDelete || fileToDelete.type !== NodeType.REG) {
            this.logError("File not found or not a regular file!");
            return false;
        }
        this.removeNode(parentDir, fileToDelete);
        return true;
    }

    save(filePath: string): boolean {
        if (!this.root) {
            console.log("Uninitialized Filesystem!");
            return false;
        }
        const content = this.serialize(this.root, '');
        fs.writeFileSync(filePath, content);
        return true;
    }

    serialize(node: FileSystemNode | null, prefix: string): string {
        if (!node) return '';
        let result = `${node.type}\t${prefix}/${node.name}\n`;
        if (node.childPtr) result += this.serialize(node.childPtr, prefix + '/' + node.name);
        if (node.siblingPtr) result += this.serialize(node.siblingPtr, prefix);
        return result;
    }

    reload(filePath: string): boolean {
        if (!fs.existsSync(filePath)) {
            console.log("No saved filesystem!");
            return false;
        }
        const lines = fs.readFileSync(filePath, 'utf-8').trim().split('\n');
        lines.forEach(line => {
            const [type, path] = line.split('\t').map(s => s.trim());
            if (type === NodeType.DIR) {
                this.mkdir(path);
            } else {
                this.creat(path);
            }
        });
        return true;
    }

    menu(): boolean {
        console.log("Command List");
        console.log("------------");
        console.log("mkdir pathname : make a new directory for a given pathname");
        console.log("rmdir pathname : remove the directory, if it is empty");
        console.log("cd [pathname]  : change CWD to pathname, or to / if no pathname");
        console.log("ls [pathname]  : list the directory contents of pathname or CWD");
        console.log("pwd            : print the (absolute) pathname of CWD");
        console.log("creat pathname : create a FILE node");
        console.log("rm pathname    : remove the FILE node");
        console.log("save filename  : save the current file system tree as a file");
        console.log("reload filename: construct a file system tree from a file");
        console.log("menu           : show a menu of valid commands");
        console.log("quit           : save the file system tree, then terminate the program");
        return true;
    }

    quit(): boolean {
        this.save('FileSystemDefault.txt');
        console.log("File system saved and program terminated.");
        process.exit(0);
    }

    logError(error: string): void {
        console.error(`Error: ${error}`);
    }

    dbname(fullPath: string): { dirname: string; basename: string } {
        let parts = fullPath.split('/').filter(p => p.length > 0);
        let basename = parts.pop() || '';
        let dirname = '/' + parts.join('/');
        return { dirname, basename };
    }

    searchDir(dirPath: string): FileSystemNode | null {
        if (!dirPath || dirPath === ".") return this.cwd;
        if (dirPath === "/") return this.root;
        let parts = dirPath.split('/').filter(p => p.length > 0);
        let current: FileSystemNode | null = this.root;
        for (let part of parts) {
            if (!current) return null;
            current = this.searchInDir(current, part);
        }
        return current;
    }

    searchInDir(dirNode: FileSystemNode, fileName: string): FileSystemNode | null {
        let child = dirNode.childPtr;
        while (child) {
            if (child.name === fileName) return child;
            child = child.siblingPtr;
        }
        return null;
    }

    insertNode(parentNode: FileSystemNode, newNode: FileSystemNode): void {
        if (!parentNode.childPtr) {
            parentNode.childPtr = newNode;
        } else {
            let lastSibling = parentNode.childPtr;
            while (lastSibling.siblingPtr) {
                lastSibling = lastSibling.siblingPtr;
            }
            lastSibling.siblingPtr = newNode;
        }
        newNode.parentPtr = parentNode;
    }

    removeNode(parentNode: FileSystemNode, nodeToRemove: FileSystemNode): void {
        if (parentNode.childPtr === nodeToRemove) {
            parentNode.childPtr = nodeToRemove.siblingPtr;
        } else {
            let current = parentNode.childPtr!;
            while (current && current.siblingPtr !== nodeToRemove) {
                current = current.siblingPtr;
            }
            if (current) {
                current.siblingPtr = nodeToRemove.siblingPtr;
            }
        }
        if (nodeToRemove.siblingPtr) {
            nodeToRemove.siblingPtr.parentPtr = parentNode;
        }
    }
}

const fileSystem = new FileSystem();
fileSystem.menu();  // Show menu of commands

