<?php

namespace App\Console\Commands\Cron;

use App\Models\Installment;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateInstallmentFine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:calculate-installment-fine';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算分期付款逾期费';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Installment::query()
            // 预加载分期付款数据，避免 N + 1 问题
            ->with(['installment'])
            ->whereHas(
                'installment',
                function ($query) {
                    // 对应的分期状态为还款中
                    $query->where('status', Installment::STATUS_REPAYING);
                }
            )
            // 还款截止日期在当期实际之前
            ->where('due_date', '<=', Carbon::now())
            // 尚未还款
            ->whereNull('paid_at')
            // 使用 chunkById 避免一次性查询太多记录
            ->chunkById(
                1000,
                function ($items) {
                    // 遍历查询出来的还款计划
                    foreach ($items as $item) {
                        // 通过 Carbon 对象的 diffInDays 直接得到逾期天数
                        $overdueDays = Carbon::now()->diffInDays($item->due_date);
                        // 本金与手续费之和
                        $base = big_number($item->base)->plus($item->fee);
                        // 计算逾期费
                        $fine = big_number($base)
                            ->multipliedBy($overdueDays)
                            ->multipliedBy($item->installment->fine_rate)
                            ->dividedBy(100, 2, RoundingMode::UP);
                        // 避免逾期费高于本金与手续费之和，使用 compareTo 方法来判断
                        // 如果 $fine 大于 $base，则 compareTo 会返回 1，等于返回 0，小于返回 -1
                        $fine = big_number($fine)->compareTo($base) === 1 ? $base : $fine;
                        $item->update(
                            [
                                'fine' => $fine,
                            ]
                        );
                    }
                }
            );

    }
}
