<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - {{ $appName }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #374151;
            background: linear-gradient(135deg, #fdf2f8 0%, #f9a8d4 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 10px 40px rgba(244, 114, 182, 0.15);
        }
        
        .header {
            background: linear-gradient(135deg, #f472b6, #ec4899);
            padding: 50px 32px;
            text-align: center;
            color: white;
        }
        
        .logo-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            text-align: center;
            line-height: 100px;
        }

        .logo-image {
            width: 120px;
            height: 120px;
            object-fit: contain;
            vertical-align: middle;
            border-radius: 16px;
        }
        
        .app-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .subtitle {
            font-size: 16px; /* Diperbesar dari 14px */
            opacity: 0.9;
        }
        
        .content {
            padding: 48px 32px;
            background: white;
        }
        
        .greeting {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
            text-align: center;
        }
        
        .message {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 40px;
            text-align: center;
            line-height: 1.7;
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .reset-button {
            display: inline-block;
            background: #ec4899;
            color: white !important;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3);
            transition: all 0.2s ease;
            min-width: 200px;
        }
        
        .reset-button:hover {
            background: #db2777;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(236, 72, 153, 0.4);
        }
        
        .expire-notice {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 16px;
            margin: 32px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .expire-icon {
            width: 20px;
            height: 20px;
            fill: #d97706;
            flex-shrink: 0;
        }
        
        .expire-text {
            color: #92400e;
            font-size: 14px;
            font-weight: 500;
        }
        
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 32px 0;
        }
        
        .link-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 32px 0;
        }
        
        .link-title {
            font-weight: 500;
            color: #374151;
            margin-bottom: 12px;
            font-size: 14px;
            text-align: center;
        }
        
        .link-url {
            font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
            font-size: 12px;
            color: #6b7280;
            word-break: break-all;
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            line-height: 1.4;
        }
        
        .support-message {
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            margin-top: 24px;
        }
        
        .footer {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 24px 32px;
            text-align: center;
        }
        
        .footer-text {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .footer-highlight {
            color: #ec4899;
            font-weight: 600;
        }
        
        .footer-note {
            font-size: 12px;
            color: #9ca3af;
            margin-bottom: 16px;
        }
        
        .footer-copyright {
            font-size: 12px;
            color: #9ca3af;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Mobile Responsive */
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .content, .header {
                padding: 32px 20px;
            }
            
            .greeting {
                font-size: 20px;
            }
            
            .message {
                font-size: 15px;
                max-width: 100%;
            }
            
            .reset-button {
                padding: 16px 24px;
                font-size: 15px;
                min-width: 180px;
            }
            
            .logo-container {
                width: 100px;
                height: 100px;
            }
            
            .logo-image {
                width: 100px;
                height: 100px;
            }
            
            .app-name {
                font-size: 22px;
            }
            
            .subtitle {
                font-size: 14px;
            }
            
            .link-url {
                font-size: 11px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo-container">
                <img src="https://firebasestorage.googleapis.com/v0/b/seputipy.appspot.com/o/covers%2Fmeddiscus_logo.png?alt=media" alt="Meddiscus Logo" class="logo-image">
            </div>
            <div class="app-name">{{ $appName }}</div>
            <div class="subtitle">Reset Password Aman</div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <h1 class="greeting">Halo {{ $user->name }}!</h1>
            
            <p class="message">
                Kami menerima permintaan untuk mereset password akun Anda. 
                Klik tombol di bawah untuk melanjutkan proses reset password dengan aman.
            </p>
            
            <!-- Reset Button -->
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button">
                    Reset Password Sekarang
                </a>
            </div>
            
            <!-- Expire Notice -->
            <div class="expire-notice">
                <svg class="expire-icon" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
                <div class="expire-text">
                    Link ini akan kedaluwarsa dalam <strong>{{ $expireTime }} menit</strong>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <!-- Alternative Link -->
            <div class="link-section">
                <div class="link-title">
                    Jika tombol tidak berfungsi, salin link berikut:
                </div>
                <div class="link-url">{{ $resetUrl }}</div>
            </div>
            
            <p class="support-message">
                Butuh bantuan? Hubungi tim support kami yang siap membantu 24/7.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="footer-text">
                Terima kasih telah menggunakan <span class="footer-highlight">{{ $appName }}</span>
            </div>
            <div class="footer-note">
                Email ini dikirim secara otomatis, mohon jangan membalas.
            </div>
            <div class="footer-copyright">
                © {{ date('Y') }} {{ $appName }}. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>