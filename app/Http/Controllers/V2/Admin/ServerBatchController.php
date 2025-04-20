<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServerBatchRequest;
use App\Services\ServerBatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServerBatchController extends Controller
{
    protected $serverBatchService;

    public function __construct(ServerBatchService $serverBatchService)
    {
        $this->serverBatchService = $serverBatchService;
    }

    public function batchCreate(ServerBatchRequest $request)
    {
        $nodes = $request->input('nodes');
        
        DB::beginTransaction();
        try {
            $result = $this->serverBatchService->batchCreateServers($nodes);
            DB::commit();
            return $this->success($result);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail([500, $e->getMessage()]);
        }
    }

    public function batchUpdate(ServerBatchRequest $request)
    {
        $nodes = $request->input('nodes');
        
        DB::beginTransaction();
        try {
            $result = $this->serverBatchService->batchUpdateServers($nodes);
            DB::commit();
            return $this->success($result);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail([500, $e->getMessage()]);
        }
    }

    public function batchDelete(Request $request)
    {
        $nodeIds = $request->input('node_ids');
        if (empty($nodeIds) || !is_array($nodeIds)) {
            return $this->fail([400, '节点ID列表不能为空']);
        }
        
        DB::beginTransaction();
        try {
            $result = $this->serverBatchService->batchDeleteServers($nodeIds);
            DB::commit();
            return $this->success($result);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail([500, $e->getMessage()]);
        }
    }

    public function templates()
    {
        try {
            $templates = $this->serverBatchService->getServerTemplates();
            return $this->success($templates);
        } catch (\Exception $e) {
            return $this->fail([500, $e->getMessage()]);
        }
    }
}
