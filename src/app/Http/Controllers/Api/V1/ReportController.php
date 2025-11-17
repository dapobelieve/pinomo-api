<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\JournalEntryItem;
use App\Models\GLAccount;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function transactionReport(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'transaction_type' => 'nullable|string|in:deposit,withdrawal,transfer',
            'status' => 'nullable|string|in:pending,completed,failed,reversed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Transaction::query()
            ->whereBetween('created_at', [$request->start_date, $request->end_date]);

        if ($request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->transaction_type) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $transactions = $query->paginate($request->per_page ?? 15);

        // Calculate aggregates
        $aggregates = Transaction::query()
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->when($request->account_id, function ($q) use ($request) {
                $q->where('account_id', $request->account_id);
            })
            ->where('status', 'completed')
            ->select(
                DB::raw('SUM(CASE WHEN transaction_type = "deposit" THEN amount ELSE 0 END) as total_deposits'),
                DB::raw('SUM(CASE WHEN transaction_type = "withdrawal" THEN amount ELSE 0 END) as total_withdrawals'),
                DB::raw('COUNT(*) as total_transactions')
            )
            ->first();

        return ResponseUtils::success([
            'transactions' => $transactions,
            'aggregates' => $aggregates
        ]);
    }

    public function accountStatement(Request $request, Account $account): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $transactions = Transaction::where('account_id', $account->id)
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->orderBy('created_at')
            ->get();

        $openingBalance = Transaction::where('account_id', $account->id)
            ->where('created_at', '<', $request->start_date)
            ->where('status', 'completed')
            ->sum(DB::raw('CASE 
                WHEN transaction_type = "deposit" THEN amount 
                WHEN transaction_type = "withdrawal" THEN -amount 
                END'));

        return ResponseUtils::success([
            'account' => $account,
            'period' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
            'opening_balance' => $openingBalance,
            'closing_balance' => $account->available_balance,
            'transactions' => $transactions,
        ]);
    }

    public function auditTrail(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|uuid|exists:users,id',
            'action_type' => 'nullable|string',
            'resource_type' => 'nullable|string',
            'resource_id' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AuditLog::query()
            ->with('user')
            ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($request->user_id, function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            })
            ->when($request->action_type, function ($q) use ($request) {
                $q->where('action_type', $request->action_type);
            })
            ->when($request->resource_type, function ($q) use ($request) {
                $q->where('resource_type', $request->resource_type);
            })
            ->when($request->resource_id, function ($q) use ($request) {
                $q->where('resource_id', $request->resource_id);
            })
            ->latest();

        return ResponseUtils::success($query->paginate($request->per_page ?? 15));
    }

    public function glAccountSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'gl_account_code' => 'nullable|string|exists:gl_accounts,account_code',
        ]);

        $query = JournalEntryItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'journal_entry_items.gl_account_id')
            ->whereBetween('journal_entries.entry_date', [$request->start_date, $request->end_date])
            ->where('journal_entries.status', 'posted')
            ->when($request->gl_account_code, function ($q) use ($request) {
                $q->where('gl_accounts.account_code', $request->gl_account_code);
            })
            ->select(
                'gl_accounts.account_code',
                'gl_accounts.account_name',
                'gl_accounts.account_type',
                DB::raw('SUM(CASE WHEN journal_entry_items.entry_type = "debit" THEN amount ELSE 0 END) as total_debits'),
                DB::raw('SUM(CASE WHEN journal_entry_items.entry_type = "credit" THEN amount ELSE 0 END) as total_credits'),
                DB::raw('SUM(CASE WHEN journal_entry_items.entry_type = "debit" THEN amount ELSE -amount END) as net_change')
            )
            ->groupBy('gl_accounts.id', 'gl_accounts.account_code', 'gl_accounts.account_name', 'gl_accounts.account_type')
            ->orderBy('gl_accounts.account_code');

        return ResponseUtils::success([
            'period' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
            'accounts' => $query->get()
        ]);
    }

    public function trialBalance(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'as_of_date' => 'required|date',
        ]);

        $query = JournalEntryItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'journal_entry_items.gl_account_id')
            ->where('journal_entries.entry_date', '<=', $request->as_of_date)
            ->where('journal_entries.status', 'posted')
            ->select(
                'gl_accounts.account_code',
                'gl_accounts.account_name',
                'gl_accounts.account_type',
                DB::raw('SUM(CASE WHEN journal_entry_items.entry_type = "debit" THEN amount ELSE 0 END) as debit_balance'),
                DB::raw('SUM(CASE WHEN journal_entry_items.entry_type = "credit" THEN amount ELSE 0 END) as credit_balance')
            )
            ->groupBy('gl_accounts.id', 'gl_accounts.account_code', 'gl_accounts.account_name', 'gl_accounts.account_type')
            ->orderBy('gl_accounts.account_code');

        $accounts = $query->get();
        $totals = [
            'total_debits' => $accounts->sum('debit_balance'),
            'total_credits' => $accounts->sum('credit_balance'),
        ];

        return ResponseUtils::success([
            'as_of_date' => $request->as_of_date,
            'accounts' => $accounts,
            'totals' => $totals
        ]);
    }

    public function journalEntries(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'gl_account_code' => 'nullable|string|exists:gl_accounts,account_code',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|string',
            'status' => 'nullable|string|in:draft,posted,voided',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = JournalEntry::with(['items.glAccount', 'createdByUser', 'postedByUser'])
            ->whereBetween('entry_date', [$request->start_date, $request->end_date])
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when($request->reference_type, function ($q) use ($request) {
                $q->where('reference_type', $request->reference_type);
            })
            ->when($request->reference_id, function ($q) use ($request) {
                $q->where('reference_id', $request->reference_id);
            })
            ->when($request->gl_account_code, function ($q) use ($request) {
                $q->whereHas('items.glAccount', function ($q) use ($request) {
                    $q->where('account_code', $request->gl_account_code);
                });
            })
            ->orderBy('entry_date', 'desc')
            ->orderBy('entry_number', 'desc');

        return ResponseUtils::success($query->paginate($request->per_page ?? 15));
    }

    public function revenueReport(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'nullable|string|in:day,week,month',
        ]);

        // Get all income type GL accounts
        $incomeAccounts = GLAccount::where('account_type', 'income')->pluck('id');

        $query = JournalEntryItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'journal_entry_items.gl_account_id')
            ->whereIn('gl_accounts.id', $incomeAccounts)
            ->whereBetween('journal_entries.entry_date', [$request->start_date, $request->end_date])
            ->where('journal_entries.status', 'posted');

        // Group by time period if specified
        if ($request->group_by) {
            $dateFormat = match($request->group_by) {
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                default => '%Y-%m-%d'
            };

            $query->select(
                DB::raw("DATE_FORMAT(journal_entries.entry_date, '{$dateFormat}') as period"),
                'gl_accounts.account_code',
                'gl_accounts.account_name',
                DB::raw('SUM(CASE WHEN journal_entry_items.entry_type = "credit" THEN amount ELSE 0 END) as revenue')
            )
            ->groupBy('period', 'gl_accounts.account_code', 'gl_accounts.account_name')
            ->orderBy('period');

            $results = $query->get()->groupBy('period');
        } else {
            // Just group by account if no time grouping specified
            $query->select(
                'gl_accounts.account_code',
                'gl_accounts.account_name',
                DB::raw('SUM(CASE WHEN journal_entry_items.entry_type = "credit" THEN amount ELSE 0 END) as revenue')
            )
            ->groupBy('gl_accounts.account_code', 'gl_accounts.account_name');

            $results = $query->get();
        }

        // Calculate totals
        $totalRevenue = $results->sum('revenue');

        return ResponseUtils::success([
            'period' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'group_by' => $request->group_by ?? 'none'
            ],
            'revenue_data' => $results,
            'total_revenue' => $totalRevenue
        ]);
    }
}