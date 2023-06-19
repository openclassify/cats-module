<?php namespace Visiosoft\CatsModule\Category;

use Anomaly\FilesModule\File\Contract\FileRepositoryInterface;
use Anomaly\FilesModule\File\FileUploader;
use Anomaly\FilesModule\Folder\Contract\FolderRepositoryInterface;
use Anomaly\Streams\Platform\Application\Application;
use Anomaly\Streams\Platform\Message\MessageBag;
use Illuminate\Http\UploadedFile;
use Visiosoft\AdvsModule\Adv\Contract\AdvRepositoryInterface;
use Visiosoft\CatsModule\Category\Contract\CategoryRepositoryInterface;
use Anomaly\Streams\Platform\Entry\EntryRepository;
use Illuminate\Support\Facades\DB;

class CategoryRepository extends EntryRepository implements CategoryRepositoryInterface
{

    protected $model;
    protected $advRepository;

    public function __construct(CategoryModel $model, AdvRepositoryInterface $advRepository)
    {
        $this->model = $model;
        $this->advRepository = $advRepository;
    }

    public function getMainCategories()
    {
        return $this->newQuery()
            ->where('parent_category_id', null)
            ->orderBy('sort_order')
            ->get();
    }

    public function getSubCatById($id)
    {
        $cats = $this->model->newQuery()
            ->where('parent_category_id', $id)
            ->get();

        foreach ($cats as $cat) {
            $subCount = $this->model->newQuery()->where('parent_category_id', $cat->id)->count();
            $cat->hasChild = !!$subCount;
        }

        return $cats;
    }

    public function getCategoriesLevel2()
    {
        $dBName = app(Application::class)->tablePrefix() . 'cats_category';
        $dBNamet = $dBName . '_translations';

        $catsDB = DB::table((DB::raw($dBName . ' c1')))
            ->select(
                DB::raw('c1.id'),
                DB::raw('c1.slug'),
                DB::raw('c1.icon'),
                DB::raw('c1.count'),
                DB::raw('c1.parent_category_id'),
                DB::raw('t1.name'),
                DB::raw('t1.description'),

                DB::raw('c2.id as c2_id'),
                DB::raw('c2.slug as c2_slug'),
                DB::raw('c2.count as c2_count'),
                DB::raw('c2.parent_category_id as c2_parent_category_id'),
                DB::raw('t2.name as c2_name')
            )
            ->leftJoin((DB::raw($dBName . ' c2')), DB::raw('c2.parent_category_id'), '=', DB::raw('c1.id'))
            ->leftJoin((DB::raw($dBNamet . ' t1')), DB::raw('c1.id'), '=', DB::raw('t1.entry_id'))
            ->leftJoin((DB::raw($dBNamet . ' t2')), function ($join) {
                $join->on(DB::raw('c2.id'), '=', DB::raw('t2.entry_id'))
                    ->where(DB::raw('t2.locale'), Request()->session()->get('_locale', setting_value('streams::default_locale')));
            })
            ->where(DB::raw('t1.locale'), Request()->session()->get('_locale', setting_value('streams::default_locale')))
            ->where(DB::raw("c1.deleted_at"), NULL)
            ->where(DB::raw("c2.deleted_at"), NULL)
            ->whereNull(DB::raw("c1.parent_category_id"))
            ->orderBy(DB::raw("c1.sort_order"))
            ->orderBy(DB::raw("c2.sort_order"))
            ->get();
        $cats = collect([]);
        $cats->subcats = $catsDB;
        $cats->maincats = $catsDB->unique('id');
        return $cats;
    }

    public function getCategoriesLevel3()
    {
        $dBName = app(Application::class)->tablePrefix() . 'cats_category';
        $dBNamet = $dBName . '_translations';

        $catsDB = DB::table((DB::raw($dBName . ' c1')))
            ->select(
                DB::raw('c1.id'),
                DB::raw('c1.slug'),
                DB::raw('c1.icon'),
                DB::raw('c1.count'),
                DB::raw('c1.parent_category_id'),
                DB::raw('t1.name'),

                DB::raw('c2.id as c2_id'),
                DB::raw('c2.slug as c2_slug'),
                DB::raw('c2.icon as c2_icon'),
                DB::raw('c2.count as c2_count'),
                DB::raw('c2.parent_category_id as c2_parent_category_id'),
                DB::raw('t2.name as c2_name'),

                DB::raw('c3.id as c3_id'),
                DB::raw('c3.icon as c3_icon'),
                DB::raw('c3.slug as c3_slug'),
                DB::raw('c3.count as c3_count'),
                DB::raw('c3.parent_category_id as c3_parent_category_id'),
                DB::raw('t3.name as c3_name')
            )
            ->leftJoin((DB::raw($dBName . ' c2')), DB::raw('c2.parent_category_id'), '=', DB::raw('c1.id'))
            ->leftJoin((DB::raw($dBName . ' c3')), DB::raw('c3.parent_category_id'), '=', DB::raw('c2.id'))
            ->leftJoin((DB::raw($dBNamet . ' t1')), DB::raw('c1.id'), '=', DB::raw('t1.entry_id'))
            ->leftJoin((DB::raw($dBNamet . ' t2')), function ($join) {
                $join->on(DB::raw('c2.id'), '=', DB::raw('t2.entry_id'))
                    ->where(DB::raw('t2.locale'), Request()->session()->get('_locale', setting_value('streams::default_locale')));
            })
            ->leftJoin((DB::raw($dBNamet . ' t3')), function ($join) {
                $join->on(DB::raw('c3.id'), '=', DB::raw('t3.entry_id'))
                    ->where(DB::raw('t3.locale'), Request()->session()->get('_locale', setting_value('streams::default_locale')));
            })
            ->where(DB::raw('t1.locale'), Request()->session()->get('_locale', setting_value('streams::default_locale')))
            ->where(DB::raw("c1.deleted_at"), NULL)
            ->where(DB::raw("c2.deleted_at"), NULL)
            ->where(DB::raw("c3.deleted_at"), NULL)
            ->whereNull(DB::raw("c1.parent_category_id"))
            ->orderBy(DB::raw("c1.sort_order"))
            ->orderBy(DB::raw("c2.sort_order"))
            ->orderBy(DB::raw("c3.sort_order"));

        $cats = collect([]);
        $cats->maincats = $catsDB->get()->unique('id');
        $cats->subcats = $catsDB->get();
        $cats->subcats_sub = $catsDB->where(DB::raw('c3.parent_category_id'), '=', DB::raw('c2.id'))->get();

        return $cats;
    }

    public function getCategoryById($id)
    {
        return $this->newQuery()
            ->where('parent_category_id', $id)
            ->where('deleted_at', null)
            ->orderBy('sort_order')->get();
    }

    public function findBySlug($slug)
    {
        return $this->newQuery()
            ->where('slug', $slug)
            ->first();
    }

    public function getParentCategoryById($id)
    {
        if ($category = $this->find($id)) {
            $parents_count = ($category->parent_category_id) ? 1 : 0;
            $parents[] = $category;
            for ($i = 0; $i < $parents_count; $i++) {
                if ($category = $this->find($category->parent_category_id)) {
                    $parents[] = $category;
                    $parents_count++;
                }
            }
            return $parents;
        }
        return null;
    }

    public function getParentCategoryByOrder($id)
    {
        return array_reverse($this->getParentCategoryById($id));
    }

    public function getLevelById($id)
    {
        $parents = $this->getParentCategoryById($id);
        return (is_array($parents)) ? count($parents) : null;
    }

    public function getCategoriesByName($keyword)
    {
        $cats = DB::table('cats_category');

        $cats = $cats->where('name', 'like', $keyword . '%')
            ->whereRaw('deleted_at IS NULL');

        $cats = $cats->leftJoin('cats_category_translations', function ($join) {
            $join->on('cats_category.id', '=', 'cats_category_translations.entry_id');
            $join->whereIn('cats_category_translations.locale', [config('app.locale'), setting_value('streams::default_locale'), 'en']);//active lang
        })
            ->select('cats_category.*', 'cats_category_translations.name as name')
            ->orderBy('id', 'DESC')->groupBy(['cats_category.id'])->get();

        return $cats;
    }

    public function getDeletedCategories()
    {
        return $this->model->withTrashed()->newQuery()->whereNotNull('deleted_at')->limit(100)->get();
    }

    public function getMainAndSubCats()
    {
        $dBName = 'cats_category';
        $dBNamet = $dBName . '_translations';

        $catsDB = DB::table($dBName . ' as c1')
            ->select(
                'c1.id',
                'c1.slug',
                'c1.parent_category_id',
                'c1.icon',
                'c1.count',
                'c1.image_id',
                't1.name',
                'c2.id as c2_id',
                'c2.slug as c2_slug',
                'c2.icon as c2_icon',
                'c2.count as c2_count',
                'c2.parent_category_id as c2_parent_category_id',
                't2.name as c2_name'
            )
            ->leftJoin($dBName . ' as c2', function ($join) {
                $join->on('c2.parent_category_id', '=', 'c1.id')
                    ->whereNull('c2.deleted_at');
            })
            ->leftJoin($dBNamet . ' as t1', function ($join) use ($dBNamet) {
                $join->on('c1.id', '=', 't1.entry_id')
                    ->where('t1.locale', Request()->session()->get('_locale', setting_value('streams::default_locale')));
            })
            ->leftJoin($dBNamet . ' as t2', function ($join) use ($dBNamet) {
                $join->on('c2.id', '=', 't2.entry_id')
                    ->where('t2.locale', Request()->session()->get('_locale', setting_value('streams::default_locale')));
            })
            ->whereNull('c1.deleted_at')
            ->whereNull('c1.parent_category_id')
            ->orderBy('c1.sort_order')
            ->orderBy('c2.sort_order')
            ->get();
        $cats = collect([]);
        $cats->subcats = $catsDB;
        $cats->maincats = $catsDB->unique('id');
        return $cats;
    }

    public function noMetaReport()
    {
        $categories = $this->newQuery()
            ->select('name as category', 'cats_category.id')
            ->where(function ($q) {
                $q->whereNull('seo_keyword')
                    ->orWhereNull('seo_description')->orWhereNull('seo_title');
            })
            ->leftJoin('cats_category_translations as cats_trans', function ($join) {
                $join->on('cats_category.id', '=', 'cats_trans.entry_id');
                $join->whereIn('locale', [config('app.locale'), setting_value('streams::default_locale'), 'en']);
            });

        if ($search = request('search.value')) {
            $categories = $categories->where('name', 'LIKE', "%$search%");
        }

        if ($orderDir = request('order.0.dir')) {
            $categories = $categories->orderBy('category', $orderDir);
        }

        $start = request('start');
        $limit = request('length') ?: 10;
        $page = $start ? $start / $limit + 1 : 1;

        $categories = $categories->paginate($limit, ['*'], 'page', $page);

        return $categories;
    }

    public function setCategoryIcon($category_id, $r_file)
    {
        $uploader = app(FileUploader::class);
        $fileRepository = app(FileRepositoryInterface::class);
        $folderRepository = app(FolderRepositoryInterface::class);

        $type = explode('.', $r_file->getClientOriginalName());
        $type = end($type);

        $filename = $category_id . "." . $type;

        if (!$category = $this->find($category_id)) {
            throw new \Exception(trans('visiosoft.module.cats::message.not_found', ['name' => 'Category']), 404);
            die;
        };

        if (!$folder = $folderRepository->findBySlug('category_icon')) {
            throw new \Exception(trans('visiosoft.module.cats::message.not_found', ['name' => "'category_icon' folder"]), 404);
            die;
        };

        if ($file = $fileRepository->findByNameAndFolder($filename, $folder)) {
            $file->forceDelete();
        }

        $file = new UploadedFile($r_file->getPathname(),
            $filename,
            $r_file->getClientMimeType(),
            $r_file->getError());

        $file = $uploader->upload($file, $folder);

        $url = route('anomaly.module.files::files.view', ['folder' => $folder->slug, 'name' => $file->name]);

        $category->setCategoryIconUrl($url);
    }
}
