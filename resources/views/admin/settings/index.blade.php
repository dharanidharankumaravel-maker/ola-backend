@extends('admin.layouts.app')

@section('title', 'Alo Admin - System & Pricing Settings')
@section('page_title', 'System & Pricing Settings')

@section('content')

    <div class="card" style="max-width: 960px;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Mobility Configuration & Pricing Matrix</h3>
                <p class="card-subtitle">Set base fares, distance rates, platform commission, and app metadata.</p>
            </div>
            <span class="badge badge-info">System Config</span>
        </div>

        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf

            <!-- 1. General App Settings -->
            <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                1. General Platform Information
            </h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div class="form-group">
                    <label class="form-label">Application Name</label>
                    <input type="text" name="app_name" class="form-control" value="{{ $settings['app_name'] ?? 'Alo Mobility' }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Currency Symbol</label>
                    <input type="text" name="currency_symbol" class="form-control" value="{{ $settings['currency_symbol'] ?? '₹' }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">24/7 Helpline Support Number</label>
                    <input type="text" name="helpline_number" class="form-control" value="{{ $settings['helpline_number'] ?? '1800-256-4444' }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Customer Support Email</label>
                    <input type="email" name="support_email" class="form-control" value="{{ $settings['support_email'] ?? 'support@alo.com' }}" required>
                </div>
            </div>

            <!-- 2. Platform Commission & Tax -->
            <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                2. Commission & Tax Rates
            </h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div class="form-group">
                    <label class="form-label">Platform Commission (%)</label>
                    <input type="number" step="0.1" name="commission_percentage" class="form-control" value="{{ $settings['commission_percentage'] ?? '15' }}" required>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">Deducted from driver trip fare upon completion.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">GST / Government Tax (%)</label>
                    <input type="number" step="0.1" name="gst_percentage" class="form-control" value="{{ $settings['gst_percentage'] ?? '5' }}" required>
                </div>
            </div>

            <!-- 3. Fare Matrix by Vehicle Type -->
            <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                3. Ride Fare Matrix (INR ₹)
            </h4>

            <div style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 24px;">
                <table class="data-table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Vehicle Category</th>
                            <th>Base Fare (₹)</th>
                            <th>Per KM Rate (₹/km)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Bike</strong></td>
                            <td><input type="number" step="0.1" name="fare_bike_base" class="form-control" value="{{ $settings['fare_bike_base'] ?? '25' }}" required></td>
                            <td><input type="number" step="0.1" name="fare_bike_per_km" class="form-control" value="{{ $settings['fare_bike_per_km'] ?? '8' }}" required></td>
                        </tr>
                        <tr>
                            <td><strong>Auto Rickshaw</strong></td>
                            <td><input type="number" step="0.1" name="fare_auto_base" class="form-control" value="{{ $settings['fare_auto_base'] ?? '35' }}" required></td>
                            <td><input type="number" step="0.1" name="fare_auto_per_km" class="form-control" value="{{ $settings['fare_auto_per_km'] ?? '12' }}" required></td>
                        </tr>
                        <tr>
                            <td><strong>Mini (Hatchback)</strong></td>
                            <td><input type="number" step="0.1" name="fare_mini_base" class="form-control" value="{{ $settings['fare_mini_base'] ?? '50' }}" required></td>
                            <td><input type="number" step="0.1" name="fare_mini_per_km" class="form-control" value="{{ $settings['fare_mini_per_km'] ?? '14' }}" required></td>
                        </tr>
                        <tr>
                            <td><strong>Sedan (Comfort)</strong></td>
                            <td><input type="number" step="0.1" name="fare_sedan_base" class="form-control" value="{{ $settings['fare_sedan_base'] ?? '70' }}" required></td>
                            <td><input type="number" step="0.1" name="fare_sedan_per_km" class="form-control" value="{{ $settings['fare_sedan_per_km'] ?? '18' }}" required></td>
                        </tr>
                        <tr>
                            <td><strong>SUV (6-Seater)</strong></td>
                            <td><input type="number" step="0.1" name="fare_suv_base" class="form-control" value="{{ $settings['fare_suv_base'] ?? '100' }}" required></td>
                            <td><input type="number" step="0.1" name="fare_suv_per_km" class="form-control" value="{{ $settings['fare_suv_per_km'] ?? '24' }}" required></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Save All Settings</button>
            </div>
        </form>
    </div>

@endsection
