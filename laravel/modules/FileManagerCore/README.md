# File Manager Core

This module aims to migrate UniSharp/laravel-filemanager package to a simplified yet extendable structure.

## V3 API

```
$file = Lfm::import();
$file = new LfmFile($file_path);

// set
$file->move($new_path);
$file->rename($new_name);
$file->resize();
$file->crop();
$file->delete();

// get
$file->url();
$file->name();
$file->path();
$file->size();
$file->mimeType();
$file->extension();
```

```
$directory = Lfm::createFolder();
$directory = new LfmDirectory($directory_path);

// get
$directory->name();
$directory->path();
$directory->files();

// set
$directory->move();
$directory->rename();
$directory->delete();
```
