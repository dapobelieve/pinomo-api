<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntry\StoreJournalEntryRequest;
use App\Http\Requests\JournalEntry\UpdateJournalEntryRequest;
use App\Models\JournalEntry;
use App\Models\GLAccount;
use App\Models\Transaction;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = JournalEntry::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('date_from')) {
                $query->where('entry_date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('entry_date', '<=', $request->date_to);
            }
            if ($request->has('reference_type')) {
                $query->where('reference_type', $request->reference_type);
            }

            $entries = $query->with(['items.glAccount', 'createdByUser', 'postedByUser'])
                            ->orderBy('entry_date', 'desc')
                            ->paginate($request->per_page ?? 15);

            return ResponseUtils::success($entries);
        } catch (\Exception $e) {
            Log::error('Failed to fetch journal entries: ' . $e->getMessage());
            return ResponseUtils::error('Failed to fetch journal entries', 500);
        }
    }

    public function store(StoreJournalEntryRequest $request)
    {
        try {
            DB::beginTransaction();

            $journalEntry = new JournalEntry($request->except('items'));
            $journalEntry->entry_number = $this->generateEntryNumber();
            $journalEntry->created_by_user_id = auth()->id();
            $journalEntry->save();

            foreach ($request->items as $item) {
                $journalEntry->items()->create($item);
            }

            DB::commit();

            $journalEntry->load(['items.glAccount', 'createdByUser']);
            return ResponseUtils::success($journalEntry, 'Journal entry created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create journal entry: ' . $e->getMessage());
            return ResponseUtils::error('Failed to create journal entry', 500);
        }
    }

    public function show(JournalEntry $journalEntry)
    {
        try {
            $journalEntry->load(['items.glAccount', 'createdByUser', 'postedByUser']);
            return ResponseUtils::success($journalEntry);
        } catch (\Exception $e) {
            Log::error('Failed to fetch journal entry: ' . $e->getMessage());
            return ResponseUtils::error('Failed to fetch journal entry', 500);
        }
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry)
    {
        try {
            if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
                return ResponseUtils::error('Only draft entries can be updated', 422);
            }

            DB::beginTransaction();

            $journalEntry->update($request->except('items'));

            if ($request->has('items')) {
                // Delete existing items
                $journalEntry->items()->delete();
                
                // Create new items
                foreach ($request->items as $item) {
                    $journalEntry->items()->create($item);
                }
            }

            DB::commit();

            $journalEntry->load(['items.glAccount', 'createdByUser']);
            return ResponseUtils::success($journalEntry, 'Journal entry updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update journal entry: ' . $e->getMessage());
            return ResponseUtils::error('Failed to update journal entry', 500);
        }
    }

    public function destroy(JournalEntry $journalEntry)
    {
        try {
            if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
                return ResponseUtils::error('Only draft entries can be deleted', 422);
            }

            DB::beginTransaction();
            $journalEntry->items()->delete();
            $journalEntry->delete();
            DB::commit();

            return ResponseUtils::success(null, 'Journal entry deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete journal entry: ' . $e->getMessage());
            return ResponseUtils::error('Failed to delete journal entry', 500);
        }
    }

    public function post(JournalEntry $journalEntry)
    {
        try {
            if (!$journalEntry->canBePosted()) {
                return ResponseUtils::error('Journal entry cannot be posted', 422);
            }

            $journalEntry->post(auth()->id());
            $journalEntry->load(['items.glAccount', 'createdByUser', 'postedByUser']);

            return ResponseUtils::success($journalEntry, 'Journal entry posted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to post journal entry: ' . $e->getMessage());
            return ResponseUtils::error('Failed to post journal entry', 500);
        }
    }

    public function void(Request $request, JournalEntry $journalEntry)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:1000'
            ]);

            if ($journalEntry->status !== JournalEntry::STATUS_POSTED) {
                return ResponseUtils::error('Only posted entries can be voided', 422);
            }

            $journalEntry->void($request->reason);
            $journalEntry->load(['items.glAccount', 'createdByUser', 'postedByUser']);

            return ResponseUtils::success($journalEntry, 'Journal entry voided successfully');
        } catch (\Exception $e) {
            Log::error('Failed to void journal entry: ' . $e->getMessage());
            return ResponseUtils::error('Failed to void journal entry', 500);
        }
    }

    private function generateEntryNumber()
    {
        $lastEntry = JournalEntry::orderBy('created_at', 'desc')->first();
        $lastNumber = $lastEntry ? intval(substr($lastEntry->entry_number, 2)) : 0;
        return 'JE' . str_pad($lastNumber + 1, 8, '0', STR_PAD_LEFT);
    }
}