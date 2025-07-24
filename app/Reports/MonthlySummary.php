<?php

namespace App\Reports;

use App\Abstracts\Report;
use App\Models\Banking\Transaction;
use App\Models\Document\Document;
use App\Utilities\Recurring;
use Carbon\Carbon;

class MonthlySummary extends Report
{
    public $default_name = 'reports.monthly_summary';

    public $icon = 'calendar_today';

    public $type = 'summary';

    protected $total_income = 0;
    protected $total_expense = 0;
    protected $net_profit = 0;
    protected $month;
    protected $year;

    public function __construct($month = null, $year = null)
    {
        parent::__construct();
        
        $this->month = $month ?? Carbon::now()->subMonth()->month;
        $this->year = $year ?? Carbon::now()->subMonth()->year;
        
        // Set date range for the specific month
        $start_date = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $end_date = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();
        
        $this->setDateRange($start_date, $end_date);
    }

    public function setTables()
    {
        $this->tables = [
            'income' => trans_choice('general.incomes', 1),
            'expense' => trans_choice('general.expenses', 2),
        ];
    }

    public function setData()
    {
        $start_date = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $end_date = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();

        $income_transactions = Transaction::with('recurring')
            ->income()
            ->isNotTransfer()
            ->whereBetween('paid_at', [$start_date, $end_date])
            ->where('company_id', company_id());

        $expense_transactions = Transaction::with('recurring')
            ->expense()
            ->isNotTransfer()
            ->whereBetween('paid_at', [$start_date, $end_date])
            ->where('company_id', company_id());

        switch ($this->getBasis()) {
            case 'cash':
                // Incomes
                $incomes = $income_transactions->get();
                $this->total_income = $incomes->sum('amount');

                // Expenses
                $expenses = $expense_transactions->get();
                $this->total_expense = $expenses->sum('amount');

                break;
            default:
                // Invoices
                $invoices = Document::invoice()
                    ->with('recurring', 'transactions', 'items')
                    ->accrued()
                    ->whereBetween('issued_at', [$start_date, $end_date])
                    ->where('company_id', company_id())
                    ->get();
                
                $this->total_income += $invoices->sum('amount');

                // Incomes (non-document)
                $incomes = $income_transactions->isNotDocument()->get();
                $this->total_income += $incomes->sum('amount');

                // Bills
                $bills = Document::bill()
                    ->with('recurring', 'transactions', 'items')
                    ->accrued()
                    ->whereBetween('issued_at', [$start_date, $end_date])
                    ->where('company_id', company_id())
                    ->get();
                
                $this->total_expense += $bills->sum('amount');

                // Expenses (non-document)
                $expenses = $expense_transactions->isNotDocument()->get();
                $this->total_expense += $expenses->sum('amount');

                break;
        }

        $this->net_profit = $this->total_income - $this->total_expense;
    }

    public function getTotalIncome()
    {
        return $this->total_income;
    }

    public function getTotalExpense()
    {
        return $this->total_expense;
    }

    public function getNetProfit()
    {
        return $this->net_profit;
    }

    public function getMonth()
    {
        return $this->month;
    }

    public function getYear()
    {
        return $this->year;
    }

    public function getMonthName()
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->format('F Y');
    }

    public function array(): array
    {
        $data = parent::array();

        $data['total_income'] = $this->has_money ? money($this->total_income)->format() : $this->total_income;
        $data['total_expense'] = $this->has_money ? money($this->total_expense)->format() : $this->total_expense;
        $data['net_profit'] = $this->has_money ? money($this->net_profit)->format() : $this->net_profit;
        $data['month'] = $this->month;
        $data['year'] = $this->year;
        $data['month_name'] = $this->getMonthName();

        return $data;
    }
}