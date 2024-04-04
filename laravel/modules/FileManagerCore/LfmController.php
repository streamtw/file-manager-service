<?php

namespace Modules\FileManagerCore;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use UniSharp\LaravelFilemanager\Events\FileIsMoving;
use UniSharp\LaravelFilemanager\Events\FileWasMoving;
use UniSharp\LaravelFilemanager\Events\FolderIsMoving;
use UniSharp\LaravelFilemanager\Events\FolderWasMoving;
use UniSharp\LaravelFilemanager\Events\FolderIsCreating;
use UniSharp\LaravelFilemanager\Events\FolderWasCreated;
use UniSharp\LaravelFilemanager\Events\FolderIsRenaming;
use UniSharp\LaravelFilemanager\Events\FolderWasRenamed;
use UniSharp\LaravelFilemanager\Events\FileIsRenaming;
use UniSharp\LaravelFilemanager\Events\FileWasRenamed;
use UniSharp\LaravelFilemanager\Events\ImageIsRenaming;
use UniSharp\LaravelFilemanager\Events\ImageWasRenamed;
use UniSharp\LaravelFilemanager\Events\FileIsDeleting;
use UniSharp\LaravelFilemanager\Events\FileWasDeleted;
use UniSharp\LaravelFilemanager\Events\FolderIsDeleting;
use UniSharp\LaravelFilemanager\Events\FolderWasDeleted;
use UniSharp\LaravelFilemanager\Events\ImageIsDeleting;
use UniSharp\LaravelFilemanager\Events\ImageWasDeleted;
use Modules\FileManagerCore\Lfm;
use Modules\FileManagerCore\LfmPath;

class LfmController extends Controller
{
    private $success_response = 'OK';

    public function __construct()
    {
        $this->applyIniOverrides();
    }

    /**
     * Set up needed functions.
     *
     * @return object|null
     */
    public function __get($var_name)
    {
        if ($var_name === 'lfm') {
            return app(LfmPath::class);
        } elseif ($var_name === 'helper') {
            return app(Lfm::class);
        }
    }

    /**
     * Show the filemanager.
     *
     * @return mixed
     */
    public function show()
    {
        return view('laravel-filemanager::index')
            ->withHelper($this->helper);
    }

    public function demo()
    {
        return view('laravel-filemanager::demo');
    }

    /**
     * Check if any extension or config is missing.
     *
     * @return array
     */
    public function getErrors()
    {
        $arr_errors = [];

        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            array_push($arr_errors, trans('laravel-filemanager::lfm.message-extension_not_found'));
        }

        if (! extension_loaded('exif')) {
            array_push($arr_errors, 'EXIF extension not found.');
        }

        if (! extension_loaded('fileinfo')) {
            array_push($arr_errors, 'Fileinfo extension not found.');
        }

        return $arr_errors;
    }

    /**
     * Overrides settings in php.ini.
     *
     * @return null
     */
    private function applyIniOverrides()
    {
        $overrides = config('lfm.php_ini_overrides', []);

        if ($overrides && is_array($overrides) && count($overrides) === 0) {
            return;
        }

        foreach ($overrides as $key => $value) {
            if ($value && $value != 'false') {
                ini_set($key, $value);
            }
        }
    }

    // TODO: remove this after refactoring RenameController and DeleteController
    protected function error($error_type, $variables = [])
    {
        return trans(Lfm::PACKAGE_NAME . '::lfm.error-' . $error_type, $variables);
    }

    /**
     * Get the images to load for a selected folder.
     *
     * @return mixed
     */
    public function getItems()
    {
        $currentPage = self::getCurrentPageFromRequest();

        $perPage = $this->helper->getPaginationPerPage();
        $items = array_merge($this->lfm->folders(), $this->lfm->files());

        return [
            'items' => array_map(function ($item) {
                return $item->fill()->attributes;
            }, array_slice($items, ($currentPage - 1) * $perPage, $perPage)),
            'paginator' => [
                'current_page' => $currentPage,
                'total' => count($items),
                'per_page' => $perPage,
            ],
            'display' => $this->helper->getDisplayMode(),
            'working_dir' => $this->lfm->path('working_dir'),
        ];
    }

    public function jsonItems()
    {
        return response()->json();
    }

    public function move()
    {
        $items = request('items');
        $folder_types = array_filter(['user', 'share'], function ($type) {
            return $this->helper->allowFolderType($type);
        });
        return view('laravel-filemanager::move')
            ->with([
                'root_folders' => array_map(function ($type) use ($folder_types) {
                    $path = $this->lfm->dir($this->helper->getRootFolder($type));

                    return (object) [
                        'name' => trans('laravel-filemanager::lfm.title-' . $type),
                        'url' => $path->path('working_dir'),
                        'children' => $path->folders(),
                        'has_next' => ! ($type == end($folder_types)),
                    ];
                }, $folder_types),
            ])
            ->with('items', $items);
    }

    public function doMove()
    {
        $target = $this->helper->input('goToFolder');
        $items = $this->helper->input('items');

        foreach ($items as $item) {
            $old_file = $this->lfm->pretty($item);
            $is_directory = $old_file->isDirectory();

            $file = $this->lfm->setName($item);

            if (!Storage::disk($this->helper->config('disk'))->exists($file->path('storage'))) {
                abort(404);
            }

            $old_path = $old_file->path();

            if ($old_file->hasThumb()) {
                $new_file = $this->lfm->setName($item)->thumb()->dir($target);
                if ($is_directory) {
                    event(new FolderIsMoving($old_file->path(), $new_file->path()));
                } else {
                    event(new FileIsMoving($old_file->path(), $new_file->path()));
                }
                $this->lfm->setName($item)->thumb()->move($new_file);
            }
            $new_file = $this->lfm->setName($item)->dir($target);
            $this->lfm->setName($item)->move($new_file);
            if ($is_directory) {
                event(new FolderWasMoving($old_path, $new_file->path()));
            } else {
                event(new FileWasMoving($old_path, $new_file->path()));
            }
        };

        return $this->success_response;
    }

    private static function getCurrentPageFromRequest()
    {
        $currentPage = (int) request()->get('page', 1);
        $currentPage = $currentPage < 1 ? 1 : $currentPage;

        return $currentPage;
    }

    /**
     * Get list of folders as json to populate treeview.
     *
     * @return mixed
     */
    public function getFolders()
    {
        $folder_types = array_filter(['user', 'share'], function ($type) {
            return $this->helper->allowFolderType($type);
        });

        return view('laravel-filemanager::tree')
            ->with([
                'root_folders' => array_map(function ($type) use ($folder_types) {
                    $path = $this->lfm->dir($this->helper->getRootFolder($type));

                    return (object) [
                        'name' => trans('laravel-filemanager::lfm.title-' . $type),
                        'url' => $path->path('working_dir'),
                        'children' => $path->folders(),
                        'has_next' => ! ($type == end($folder_types)),
                    ];
                }, $folder_types),
            ]);
    }

    /**
     * Add a new folder.
     *
     * @return mixed
     */
    public function getAddfolder()
    {
        $folder_name = $this->helper->input('name');

        $new_path = $this->lfm->setName($folder_name)->path('absolute');

        event(new FolderIsCreating($new_path));

        try {
            if ($folder_name === null || $folder_name == '') {
                return $this->helper->error('folder-name');
            } elseif ($this->lfm->setName($folder_name)->exists()) {
                return $this->helper->error('folder-exist');
            } elseif (config('lfm.alphanumeric_directory') && preg_match('/[^\w-]/i', $folder_name)) {
                return $this->helper->error('folder-alnum');
            } else {
                $this->lfm->setName($folder_name)->createFolder();
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        event(new FolderWasCreated($new_path));

        return $this->success_response;
    }

    /**
     * Upload files
     *
     * @param void
     *
     * @return JsonResponse
     */
    public function upload()
    {
        $uploaded_files = request()->file('upload');
        $error_bag = [];
        $new_filename = null;

        foreach (is_array($uploaded_files) ? $uploaded_files : [$uploaded_files] as $file) {
            try {
                $this->lfm->validateUploadedFile($file);

                $new_filename = $this->lfm->upload($file);
            } catch (\Exception $e) {
                Log::error($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                array_push($error_bag, $e->getMessage());
            } catch (\Error $e) {
                Log::error($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                array_push($error_bag, 'Some error occured during uploading.');
            }
        }

        if (is_array($uploaded_files)) {
            $response = count($error_bag) > 0 ? $error_bag : $this->success_response;
        } else { // upload via ckeditor5 expects json responses
            if (is_null($new_filename)) {
                $response = [
                    'error' => [ 'message' =>  $error_bag[0] ]
                ];
            } else {
                $url = $this->lfm->setName($new_filename)->url();

                $response = [
                    'url' => $url,
                    'uploaded' => $url
                ];
            }
        }

        return response()->json($response);
    }

    public function getRename()
    {
        $old_name = $this->helper->input('file');
        $new_name = $this->helper->input('new_name');

        $file = $this->lfm->setName($old_name);

        if (!Storage::disk($this->helper->config('disk'))->exists($file->path('storage'))) {
            abort(404);
        }

        $old_file = $this->lfm->pretty($old_name);

        $is_directory = $file->isDirectory();

        if (empty($new_name)) {
            if ($is_directory) {
                return response()->json($this->error('folder-name'), 400);
            } else {
                return response()->json($this->error('file-name'), 400);
            }
        }

        if ($is_directory && config('lfm.alphanumeric_directory') && preg_match('/[^\w-]/i', $new_name)) {
            return response()->json($this->error('folder-alnum'), 400);
        } elseif (config('lfm.alphanumeric_filename') && preg_match('/[^.\w-]/i', $new_name)) {
            return response()->json($this->error('file-alnum'), 400);
        } elseif ($this->lfm->setName($new_name)->exists()) {
            return response()->json($this->error('rename'), 400);
        }

        if (! $is_directory) {
            $extension = $old_file->extension();
            if ($extension) {
                $new_name = str_replace('.' . $extension, '', $new_name) . '.' . $extension;
            }
        }

        $new_path = $this->lfm->setName($new_name)->path('absolute');

        if ($is_directory) {
            event(new FolderIsRenaming($old_file->path(), $new_path));
        } else {
            event(new FileIsRenaming($old_file->path(), $new_path));
            event(new ImageIsRenaming($old_file->path(), $new_path));
        }

        $old_path = $old_file->path();

        if ($old_file->hasThumb()) {
            $this->lfm->setName($old_name)->thumb()
                ->move($this->lfm->setName($new_name)->thumb());
        }

        $this->lfm->setName($old_name)
            ->move($this->lfm->setName($new_name));

        if ($is_directory) {
            event(new FolderWasRenamed($old_path, $new_path));
        } else {
            event(new FileWasRenamed($old_path, $new_path));
            event(new ImageWasRenamed($old_path, $new_path));
        }

        return $this->success_response;
    }

    /**
     * Delete image and associated thumbnail.
     *
     * @return mixed
     */
    public function getDelete()
    {
        $item_names = request('items');
        $errors = [];

        foreach ($item_names as $name_to_delete) {
            $file = $this->lfm->setName($name_to_delete);

            if ($file->isDirectory()) {
                event(new FolderIsDeleting($file->path('absolute')));
            } else {
                event(new FileIsDeleting($file->path('absolute')));
                event(new ImageIsDeleting($file->path('absolute')));
            }

            if (!Storage::disk($this->helper->config('disk'))->exists($file->path('storage'))) {
                abort(404);
            }

            $file_to_delete = $this->lfm->pretty($name_to_delete);
            $file_path = $file_to_delete->path('absolute');

            if (is_null($name_to_delete)) {
                array_push($errors, $this->error('folder-name'));
                continue;
            }

            if (! $this->lfm->setName($name_to_delete)->exists()) {
                array_push($errors, $this->error('folder-not-found', ['folder' => $file_path]));
                continue;
            }

            if ($this->lfm->setName($name_to_delete)->isDirectory()) {
                if (! $this->lfm->setName($name_to_delete)->directoryIsEmpty()) {
                    array_push($errors, $this->error('delete-folder'));
                    continue;
                }

                $this->lfm->setName($name_to_delete)->delete();

                event(new FolderWasDeleted($file_path));
            } else {
                if ($file_to_delete->isImage()) {
                    $this->lfm->setName($name_to_delete)->thumb()->delete();
                }

                $this->lfm->setName($name_to_delete)->delete();

                event(new FileWasDeleted($file_path));
                event(new ImageWasDeleted($file_path));
            }
        }

        if (count($errors) > 0) {
            return response()->json($errors, 400);
        }

        return $this->success_response;
    }

    /**
     * Show crop page.
     *
     * @return mixed
     */
    public function getCrop()
    {
        return view('laravel-filemanager::crop')
            ->with([
                'working_dir' => request('working_dir'),
                'img' => $this->lfm->pretty(request('img'))
            ]);
    }

    /**
     * Crop the image (called via ajax).
     */
    public function getCropImage($overWrite = true)
    {
        $image_name = request('img');
        $image_path = $this->lfm->setName($image_name)->path('absolute');
        $crop_path = $image_path;

        if (! $overWrite) {
            $fileParts = explode('.', $image_name);
            $fileParts[count($fileParts) - 2] = $fileParts[count($fileParts) - 2] . '_cropped_' . time();
            $crop_path = $this->lfm->setName(implode('.', $fileParts))->path('absolute');
        }

        event(new ImageIsCropping($image_path));

        $crop_info = request()->only('dataWidth', 'dataHeight', 'dataX', 'dataY');

        // crop image
        // TODO: support intervention/image v3
        Image::make($image_path)
            ->crop(...array_values($crop_info))
            ->save($crop_path);

        // make new thumbnail
        $this->lfm->generateThumbnail($image_name);

        event(new ImageWasCropped($image_path));
    }

    public function getNewCropImage()
    {
        $this->getCropimage(false);
    }

    /**
     * Dipsplay image for resizing.
     *
     * @return mixed
     */
    public function getResize()
    {
        $ratio = 1.0;
        $image = request('img');

        $original_image = Image::make($this->lfm->setName($image)->path('absolute'));
        $original_width = $original_image->width();
        $original_height = $original_image->height();

        $scaled = false;

        // FIXME size should be configurable
        if ($original_width > 600) {
            $ratio = 600 / $original_width;
            $width = $original_width * $ratio;
            $height = $original_height * $ratio;
            $scaled = true;
        } else {
            $width = $original_width;
            $height = $original_height;
        }

        if ($height > 400) {
            $ratio = 400 / $original_height;
            $width = $original_width * $ratio;
            $height = $original_height * $ratio;
            $scaled = true;
        }

        return view('laravel-filemanager::resize')
            ->with('img', $this->lfm->pretty($image))
            ->with('height', number_format($height, 0))
            ->with('width', $width)
            ->with('original_height', $original_height)
            ->with('original_width', $original_width)
            ->with('scaled', $scaled)
            ->with('ratio', $ratio);
    }

    public function performResize($overWrite = true)
    {
        $image_name = request('img');
        $image_path = $this->lfm->setName(request('img'))->path('absolute');
        $resize_path = $image_path;

        if (! $overWrite) {
            $fileParts = explode('.', $image_name);
            $fileParts[count($fileParts) - 2] = $fileParts[count($fileParts) - 2] . '_resized_' . time();
            $resize_path = $this->lfm->setName(implode('.', $fileParts))->path('absolute');
        }

        event(new ImageIsResizing($image_path));
        // TODO: support intervention/image v3
        Image::make($image_path)->resize(request('dataWidth'), request('dataHeight'))->save($resize_path);
        event(new ImageWasResized($image_path));

        return $this->success_response;
    }

    public function performResizeNew()
    {
        $this->performResize(false);
    }

    public function getDownload()
    {
        $file = $this->lfm->setName(request('file'));

        if (!Storage::disk($this->helper->config('disk'))->exists($file->path('storage'))) {
            abort(404);
        }

        return Storage::disk($this->helper->config('disk'))->download($file->path('storage'));
    }
}
