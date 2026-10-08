$user = App\Models\User::find(25); Auth::login($user); $response = app()->make(App\Http\Controllers\EstablecimientoController::class)->index(6); echo json_encode($response->getData(true));
