<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Midtrans\Config;
use Midtrans\Snap;

class TransactionController extends Controller
{
    public function all(Request $request){
        $id = $request->input('id');
        $limit = $request->input('limit', 6);
        $food_id = $request->input('food_id');
        $status = $request->input('status');

        if($id){
            $transaction = Transaction::with(['food','user'])->find($id);

            if($transaction){
                return ResponseFormatter::success(
                    $transaction,
                    'Data transaction berhasil diambil'
                );
            } else{
                return ResponseFormatter::error(
                    null,
                    'Data transaction tidak ada',
                    404
                );
            }
        }

        $transaction = Transaction::with(['food','user'])->where('user_id', Auth::user()->id);

        if($food_id){
            $transaction->where('food_id', $food_id);
        }

        if($status){
            $transaction->where('status', $status);
        }

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'Data list transaction berhasil diambil'
        );
    }

    public function update(Request $request, $id){
        $transaction = Transaction::findOrFail($id);

        $transaction->update($request->all());

        return ResponseFormatter::success($transaction, 'Transaksi berhasil diperbarui');
    }

    public function checkout(Request $request){
        $request->validate([
            'food_id' => 'required|exists:food,id',
            'user_id' => 'required|exists:users,id',
            'quantity' => 'required',
            'total' => 'required',
            'status' => 'required',
        ]);

        $trasaction = Transaction::create([
            'food_id' => 'required->food_id',
            'user_id' => 'required->user_id',
            'quantity' => 'required->quantity',
            'total' => 'required->total',
            'status' => 'required->status',
            'payment_url' => 'required->payment_url',
        ]);

        //Konfigurasi Midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized= config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        //Panggil transaksi yang tadi diambil
        $trasaction = Transaction::with(['food','user'])->find($trasaction->id);

        //Membuat Transaksi mIdtrans
        $midtrans = [
            'transaction_details' => [
                'order_id' => $trasaction->id,
                'gross_amount' => (int) $trasaction->total,
            ],
            'costomer_details' => [
                'first_name' => $trasaction->user->name,
                'email' => $trasaction->user->email,
            ],
            'enabled_payments' => ['gopay','bank_transfer'],
            'vtweb' => []
        ];

        //Memanggil Midtrans
        try {
            //Ambil halaman payment midtrans
            $paymentUrl =Snap::createTransaction($midtrans)->redirect_url;
            $trasaction->payment_url = $paymentUrl;
            $trasaction->save();

             //Mengembalikan Data ke Api
             return ResponseFormatter::success($trasaction, 'Transaksi berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), 'Transaksi Gagal');
        }
    }
}
