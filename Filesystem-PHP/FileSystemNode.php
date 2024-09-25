<?php
class FileSystemNode {
    public string $name;
    public NodeType $type;
    public ?FileSystemNode $childPtr = null;
    public ?FileSystemNode $siblingPtr = null;
    public ?FileSystemNode $parentPtr = null;

    public function __construct(string $name, NodeType $type) {
        $this->name = $name;
        $this->type = $type;
    }
}