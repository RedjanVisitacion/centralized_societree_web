<?php
require_once __DIR__ . '/../../backend/redcross_backend_certificate.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - REDCROSS</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Oswald", sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .certificate-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 60px 80px;
            position: relative;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }

        /* Decorative Top Border */
        .certificate-top-border {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: linear-gradient(to bottom, #8B0000 0%, #8B0000 40%, #DAA520 40%, #DAA520 60%, #8B0000 60%);
            border-radius: 0;
        }

        .certificate-top-border::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 150px;
            height: 80px;
            background: radial-gradient(circle at top left, #DAA520 0%, #DAA520 30%, transparent 30%);
        }

        .certificate-top-border::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 80px;
            background: radial-gradient(circle at top right, #DAA520 0%, #DAA520 30%, transparent 30%);
        }

        /* Decorative Bottom Border */
        .certificate-bottom-border {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: repeating-linear-gradient(
                90deg,
                #8B0000 0px,
                #8B0000 20px,
                #DAA520 20px,
                #DAA520 40px
            );
        }

        /* Decorative Corner Patterns */
        .corner-pattern {
            position: absolute;
            width: 100px;
            height: 100px;
            opacity: 0.3;
        }

        .corner-top-left {
            top: 100px;
            left: 20px;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                #DAA520 10px,
                #DAA520 20px
            );
        }

        .corner-top-right {
            top: 100px;
            right: 20px;
            background: repeating-linear-gradient(
                -45deg,
                transparent,
                transparent 10px,
                #DAA520 10px,
                #DAA520 20px
            );
        }

        .certificate-content {
            position: relative;
            z-index: 1;
            padding-top: 40px;
        }

        .certificate-title {
            text-align: center;
            margin-bottom: 20px;
        }

        .certificate-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 3.5rem;
            font-weight: 900;
            color: #000;
            margin-bottom: 5px;
            letter-spacing: 3px;
        }

        .certificate-title h2 {
            font-family: "Playfair Display", serif;
            font-size: 2rem;
            font-weight: 700;
            color: #8B0000;
            margin-top: 0;
        }

        .separator {
            text-align: center;
            margin: 30px 0;
            font-size: 1.2rem;
            color: #000;
            letter-spacing: 5px;
        }

        .award-text {
            text-align: center;
            font-size: 1rem;
            color: #000;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .member-name {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: #8B0000;
            margin: 30px 0;
            padding: 15px 0;
            border-bottom: 2px solid #000;
            font-family: "Playfair Display", serif;
        }

        .certificate-body {
            text-align: center;
            font-size: 1.1rem;
            line-height: 1.8;
            color: #000;
            margin: 30px 0;
            font-weight: 400;
        }

        .certificate-date {
            text-align: center;
            font-size: 1rem;
            color: #000;
            margin: 30px 0;
            font-weight: 500;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            position: relative;
        }

        .signature-left,
        .signature-right {
            flex: 1;
            text-align: center;
        }

        .signature-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #000;
            margin-bottom: 5px;
            border-top: 2px solid #000;
            padding-top: 50px;
            display: inline-block;
            min-width: 200px;
        }

        .signature-title {
            font-size: 0.9rem;
            color: #555;
            margin-top: 5px;
        }

        .seal {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            border: 4px solid #8B0000;
            border-radius: 50%;
            background: radial-gradient(circle, #DAA520 0%, #F4D03F 50%, #DAA520 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }

        .seal::before {
            content: '✠';
            font-size: 2.5rem;
            color: #8B0000;
            font-weight: bold;
        }

        .action-buttons {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
        }

        .btn {
            padding: 12px 30px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-family: "Oswald", sans-serif;
            font-weight: 500;
        }

        .btn-primary {
            background: #f80305;
            color: white;
        }

        .btn-primary:hover {
            background: #d00203;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .action-buttons {
                display: none;
            }

            .certificate-container {
                box-shadow: none;
                padding: 60px 80px;
            }
        }

        @media (max-width: 768px) {
            .certificate-container {
                padding: 40px 30px;
            }

            .certificate-title h1 {
                font-size: 2.5rem;
            }

            .certificate-title h2 {
                font-size: 1.5rem;
            }

            .member-name {
                font-size: 1.8rem;
            }

            .signatures {
                flex-direction: column;
                gap: 40px;
            }

            .seal {
                position: relative;
                margin: 20px auto;
                transform: none;
            }
        }
    </style>
</head>
<body>
    <?php if ($certificate_data): ?>
        <div class="certificate-container">
            <div class="certificate-top-border"></div>
            <div class="corner-pattern corner-top-left"></div>
            <div class="corner-pattern corner-top-right"></div>
            
            <div class="certificate-content">
                <div class="certificate-title">
                    <h1>CERTIFICATE</h1>
                    <h2>of Recognition</h2>
                </div>

                <div class="separator">• • • ✦ • • •</div>

                <div class="award-text">
                    THE FOLLOWING AWARD IS GIVEN TO
                </div>

                <div class="member-name">
                    <?php echo htmlspecialchars($certificate_data['full_name']); ?>
                </div>

                <div class="certificate-body">
                    in recognition of completing the required participation time/quota under the programs and activities of the Philippine Red Cross – Oroquieta City, USTP.<br><br>
                    Your dedication, commitment, and service reflect the humanitarian values upheld by the Red Cross.
                </div>

                <div class="certificate-date">
                    Awarded this <?php echo date('j', strtotime($certificate_data['issued_at'])); ?> of <?php echo date('F', strtotime($certificate_data['issued_at'])); ?>, <?php echo date('Y', strtotime($certificate_data['issued_at'])); ?>
                </div>

                <div class="signatures">
                    <div class="signature-left">
                        <div class="signature-name">HANNAH MORALES</div>
                        <div class="signature-title">HEAD MASTER</div>
                    </div>
                    <div class="seal"></div>
                    <div class="signature-right">
                        <div class="signature-name">LARS PETERS</div>
                        <div class="signature-title">MENTOR</div>
                    </div>
                </div>
            </div>

            <div class="certificate-bottom-border"></div>
        </div>

        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-primary">Print / Save as PDF</button>
            <a href="redcross_report.php" class="btn btn-secondary">Back to Reports</a>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 50px;">
            <h2>Certificate Not Found</h2>
            <p>The requested certificate could not be found.</p>
            <a href="redcross_report.php" class="btn btn-secondary">Back to Reports</a>
        </div>
    <?php endif; ?>
</body>
</html>

