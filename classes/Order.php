<?php
/**
 * Class sederhana untuk merepresentasikan satu order laundry.
 * Data yang dipakai dashboard saat ini masih data contoh (belum dari database).
 */
class Order
{
    public $code;
    public $customer;
    public $phone;
    public $package;
    public $weight;
    public $dateIn;
    public $deadline;
    public $status;
    public $rate;

    public function __construct($code, $customer, $phone, $package, $weight, $dateIn, $deadline, $status, $rate)
    {
        $this->code = $code;
        $this->customer = $customer;
        $this->phone = $phone;
        $this->package = $package;
        $this->weight = (float) $weight;
        $this->dateIn = $dateIn;
        $this->deadline = $deadline;
        $this->status = $status;
        $this->rate = (int) $rate;
    }

    public function getTotalPrice()
    {
        return (int) round($this->weight * $this->rate);
    }

    public function getStatusLabel()
    {
        $labels = array(
            'waiting' => 'Menunggu',
            'washing' => 'Dicuci',
            'drying' => 'Dikeringkan',
            'ironing' => 'Disetrika',
            'ready' => 'Siap Diambil',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan'
        );

        return isset($labels[$this->status]) ? $labels[$this->status] : 'Belum diketahui';
    }

    public function getStatusClass()
    {
        $classes = array(
            'waiting' => 'status-waiting',
            'washing' => 'status-process',
            'drying' => 'status-process',
            'ironing' => 'status-process',
            'ready' => 'status-ready',
            'completed' => 'status-completed',
            'cancelled' => 'status-cancelled'
        );

        return isset($classes[$this->status]) ? $classes[$this->status] : 'status-waiting';
    }
}
