<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ChassisTemplate;
use App\Models\Player;
use App\Models\PlayerRig;
use App\Models\User;
use App\Rules\CleanHandle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    /**
     * GET /register — render the Inertia registration page.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * POST /register — create User + Player + Rig, then log in.
     *
     * New runners always start on the BlackHat v1.0 chassis.
     * Starting SS = 100 (flat for all rigs — chassis does not affect max SS).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'handle'   => ['required', 'string', 'min:2', 'max:24', 'unique:players,handle',
                           'regex:/^[a-zA-Z0-9_\-]+$/', new CleanHandle],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'handle.regex'  => 'Handle may only contain letters, numbers, underscores, and hyphens.',
            'handle.unique' => 'That handle is already taken.',
            'email.unique'  => 'An account with that email already exists.',
        ]);

        DB::transaction(function () use ($data) {
            // ── User ──────────────────────────────────────────────────────── //
            $user = User::create([
                'name'     => $data['handle'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // ── Player ────────────────────────────────────────────────────── //
            $player = Player::create([
                'user_id'          => $user->id,
                'handle'           => $data['handle'],
                'bounty_level'     => 0,
                'pocket_creds'     => 0,
                'wallet_creds'     => 0,
            ]);

            // ── Rig — BlackHat v1.0 starter chassis ──────────────────────── //
            $chassis = ChassisTemplate::where('name', 'BlackHat v1.0')->firstOrFail();

            // SS is a flat 100 for all rigs — chassis and OS do not affect max SS
            $startingSS = 100;

            PlayerRig::create([
                'player_id'           => $player->id,
                'chassis_template_id' => $chassis->id,
                'cpu_level'           => 0,
                'ram_level'           => 0,
                'firewall_level'      => 0,
                'storage_level'       => 0,
                'os_level'            => 0,
                'current_ss'          => $startingSS,
                'is_limping'          => false,
            ]);

            Auth::login($user, remember: false);
        });

        $request->session()->regenerate();

        return redirect('/');
    }
}
