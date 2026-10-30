<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreDocument;
use App\Services\AdminPanel\LiveOps;
use App\Services\AdminPanel\Panel;
use App\Services\AdminPanel\Resource;
use App\Services\AdminPanel\Resources\TicketsResource;
use App\Services\AdminPanel\Scope;
use App\Services\AdminPanel\Settings;
use App\Services\StoreDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The redesigned admin panel (design handoff v4): one page that talks to
 * this controller's JSON endpoints, plus the live-ops screen.
 */
class PanelController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        return view('admin-views.panel.index', [
            'boot' => [
                'resources' => Panel::config(),
                'settings' => Panel::allowed('settings') ? ['groups' => Settings::groups()] : null,
                'race' => ['groups' => Settings::raceGroups()],
                'admin' => ['name' => trim($admin->f_name . ' ' . $admin->l_name), 'first' => $admin->f_name],
                'base' => url('admin/panel'),
                'live' => route('admin.panel.live'),
                'classic' => route('admin.dashboard'),
                'logout' => route('logout'),
                'lang' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            ],
        ]);
    }

    public function counts(Request $request): JsonResponse
    {
        return response()->json(Panel::counts(Scope::normalize($request->query('mod'))));
    }

    public function home(Request $request): JsonResponse
    {
        $mod = Scope::normalize($request->query('mod'));
        return response()->json($mod === 'spots' ? Panel::spotsHome() : Panel::home($mod));
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        return response()->json(mb_strlen($term) < 2 ? [] : Panel::search($term, Scope::normalize($request->query('mod'))));
    }

    // ----------------------------------------------------------- resources

    private function resource(string $key, Request $request): Resource
    {
        abort_unless(Panel::allowed($key), 403);
        return Panel::resource($key)->withModule(Scope::normalize($request->query('mod', $request->input('mod'))));
    }

    public function list(Request $request, string $res): JsonResponse
    {
        $mod = Scope::normalize($request->query('mod'));
        return response()->json($this->resource($res, $request)->list($request, $mod));
    }

    public function export(Request $request, string $res): JsonResponse
    {
        $mod = Scope::normalize($request->query('mod'));
        return response()->json($this->resource($res, $request)->export($request, $mod));
    }

    public function show(Request $request, string $res, string $id): JsonResponse
    {
        $detail = $this->resource($res, $request)->show($id);
        abort_unless($detail, 404);
        return response()->json($detail);
    }

    public function blank(Request $request, string $res): JsonResponse
    {
        $resource = $this->resource($res, $request);
        $draft = $resource->blank(Scope::normalize($request->query('mod')));
        abort_unless($draft, 404);
        return response()->json(['row' => $draft, 'options' => $resource->options()]);
    }

    public function store(Request $request, string $res): JsonResponse
    {
        $result = $this->resource($res, $request)->create((array) $request->input('data', []), Scope::normalize($request->input('mod')));
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function update(Request $request, string $res, string $id): JsonResponse
    {
        $result = $this->resource($res, $request)->update($id, (array) $request->input('data', []));
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function destroy(Request $request, string $res, string $id): JsonResponse
    {
        $result = $this->resource($res, $request)->delete($id);
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function action(Request $request, string $res, string $id, string $action): JsonResponse
    {
        $result = $this->resource($res, $request)->act($id, $action, $request);
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:2000']);
        /** @var TicketsResource $tickets */
        $tickets = $this->resource('tickets', $request);
        $result = $tickets->reply((int) $id, $request->input('text'));
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /** MA-4: a store document's file. Kept off the public disk (ID cards). */
    public function documentFile(int $id)
    {
        abort_unless(Panel::allowed('verification'), 403);
        $doc = StoreDocument::findOrFail($id);
        $path = StoreDocumentService::DIR . '/' . $doc->file;
        $disk = Storage::disk($doc->file_disk ?: StoreDocumentService::DISK);
        abort_unless($doc->file && $disk->exists($path), 404);
        return $disk->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    // ------------------------------------------------------------ settings

    public function settings(): JsonResponse
    {
        abort_unless(Panel::allowed('settings'), 403);
        return response()->json(Settings::values());
    }

    public function saveSettings(Request $request): JsonResponse
    {
        abort_unless(Panel::allowed('settings'), 403);
        $result = Settings::save((array) $request->input('data', []));
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function race(): JsonResponse
    {
        return response()->json(['values' => Settings::raceValues(), 'jobs' => Panel::jobs()]);
    }

    public function saveRace(Request $request): JsonResponse
    {
        $result = Settings::saveRace((array) $request->input('data', []));
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function runJob(Request $request): JsonResponse
    {
        $result = Panel::runJob((string) $request->input('job'));
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    // ------------------------------------------------------------ live ops

    public function live(): View
    {
        return view('admin-views.panel.live', [
            'boot' => [
                'base' => url('admin/panel'),
                'panel' => route('admin.panel.index'),
                'lang' => app()->getLocale() === 'ar' ? 'ar' : 'en',
                'canDispatch' => Panel::allowed('orders'),
            ],
        ]);
    }

    public function liveData(Request $request): JsonResponse
    {
        abort_unless(Panel::allowed('orders'), 403);
        $zone = $request->filled('zone') ? (int) $request->query('zone') : null;
        $range = in_array($request->query('range'), ['today', '7d', '30d'], true) ? $request->query('range') : 'today';
        return response()->json(LiveOps::data($zone, $range));
    }

    public function liveAction(Request $request): JsonResponse
    {
        abort_unless(Panel::allowed('orders'), 403);
        $request->validate(['order' => 'required|integer', 'do' => 'required|in:advance,assign,reassign']);
        $order = (int) $request->input('order');
        $result = $request->input('do') === 'advance' ? LiveOps::advance($order) : LiveOps::assign($order, $request->input('do') === 'reassign');
        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
