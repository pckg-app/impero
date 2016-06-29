<?php namespace Pckg\Furs\Entity;

use Gnp\Orders\Record\Order;
use Pckg\Database\Entity;
use Pckg\Furs\Record\Furs as FursRecord;

class Furs extends Entity
{

    protected $record = FursRecord::class;

    public function getOrCreateFromOrder(Order $order) {
        $furs = (new static())->where('order_id', $order->id)->where('platform_id', $_SESSION['platform_id'])->one();

        if (!$furs) {
            $furs = new FursRecord(
                [
                    'order_id'    => $order->id,
                    'platform_id' => $_SESSION['platform_id'],
                ]
            );
        }

        $furs->requested_at = date('Y-m-d H:i:s');
        $furs->save();

        return $furs;
    }

}