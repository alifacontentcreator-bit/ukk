{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman notification, termasuk data dan aksi yang disediakan aplikasi. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F4F6EF;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F6EF;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background-color:#FFFFFF;border:1px solid #DFE7D3;border-radius:12px;">
                    <tr>
                        <td style="padding:18px 24px;background-color:#3F5230;border-radius:12px 12px 0 0;">
                            <div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;line-height:1.35;color:#FFFFFF;letter-spacing:.02em;">{{ $appName ?: config('app.name') }}</div>
                            <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.35;color:#DCE6D2;">{{ $appFooter }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <h1 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;line-height:1.3;color:#26331A;">{{ $title }}</h1>

                            @foreach($paragraphs as $paragraph)
                                <p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#3A4630;">{{ $paragraph }}</p>
                            @endforeach

                            @if($highlight)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0;width:100%;">
                                    <tr>
                                        <td align="center" style="padding:14px;background-color:#F4F6EF;border:1px solid #DFE7D3;border-radius:10px;font-family:'Courier New',Courier,monospace;font-size:22px;font-weight:700;line-height:1.3;color:#25562F;letter-spacing:.16em;">{{ $highlight }}</td>
                                    </tr>
                                </table>
                            @endif

                            @if($actionUrl)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0 10px;">
                                    <tr>
                                        <td align="center" style="background-color:#2F6B3A;border-radius:8px;">
                                            <a href="{{ $actionUrl }}" style="display:inline-block;padding:11px 18px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;line-height:1.2;color:#FFFFFF;text-decoration:none;">{{ $actionText ?: 'Buka tautan' }}</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.6;color:#6B7A5E;word-break:break-all;">
                                    Bila tombol tidak dapat dibuka, salin tautan berikut ke peramban:<br>
                                    <span style="color:#2F6B3A;">{{ $actionUrl }}</span>
                                </p>
                            @endif

                            @if($note)
                                <div style="margin-top:16px;padding-top:12px;border-top:1px solid #E6EDDA;font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.6;color:#6B7A5E;">{{ $note }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 20px;font-family:Arial,Helvetica,sans-serif;font-size:10.5px;line-height:1.6;color:#8B9781;">
                            Email otomatis dari sistem {{ $appName ?: config('app.name') }}. Mohon jangan membalas email ini.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
