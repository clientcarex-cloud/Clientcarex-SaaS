<style>
    .ccx-login-footer-container {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background-color: white;
        padding: 1rem 2rem;
        box-shadow: 0 -1px 3px 0 rgba(0, 0, 0, 0.1), 0 -1px 2px 0 rgba(0, 0, 0, 0.06);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
        color: #6b7280;
        z-index: 1000;
        font-family: 'Inter', sans-serif;
    }

    .ccx-branding-container {
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
    }

    .ccx-branding-container span {
        font-size: 11px;
        color: #7e8c9d;
        font-weight: 500;
        margin-right: 6px;
        letter-spacing: 0.3px;
    }

    .ccx-branding-container img {
        height: 20px;
        width: auto;
    }

    .ccx-healtho-card {
        display: none;
        position: absolute;
        bottom: 150%;
        right: 0;
        width: 280px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        padding: 20px;
        z-index: 1001;
        border: 1px solid #eee;
        text-align: left;
        color: #1f2937;
    }

    .ccx-healtho-card::after {
        content: '';
        position: absolute;
        top: 100%;
        right: 20px;
        border-width: 8px;
        border-style: solid;
        border-color: #fff transparent transparent transparent;
    }

    .ccx-healtho-card h5 {
        margin: 0 0 15px 0;
        font-size: 14px;
        font-weight: 700;
        color: #111827;
    }

    .ccx-healtho-card-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        color: #4b5563;
        font-size: 13px;
        text-decoration: none;
        transition: color 0.2s;
    }

    .ccx-healtho-card-item:hover {
        color: #2563eb;
    }

    .ccx-healtho-card-item i {
        width: 25px;
        color: #2563eb;
        font-size: 14px;
        margin-right: 5px;
    }

    @media (max-width: 640px) {
        .ccx-login-footer-container {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
            padding: 1rem;
        }

        .ccx-branding-container {
            justify-content: center;
        }

        .ccx-healtho-card {
            left: 50%;
            transform: translateX(-50%);
            right: auto;
            width: calc(100vw - 40px);
            max-width: 280px;
            bottom: 110%;
        }

        .ccx-healtho-card::after {
            left: 50%;
            margin-left: -8px;
            right: auto;
        }
    }
</style>

<div class="ccx-login-footer-container">
    <div class="copyright">
        Copyright &copy; <?php echo date('Y'); ?> Healthocare Private Limited. All rights reserved.
    </div>
    <div class="ccx-branding-container" id="ccxBrandingTrigger">
        <span>Powered by</span>
        <img src="<?php echo base_url('modules/ccx_login/assets/images/healtho_logo.png'); ?>" alt="Clientcarex">

        <div class="ccx-healtho-card" id="ccxHealthoCard">
            <h5>Get our service for your Healthcare Brand</h5>
            <a href="tel:+919700730044" class="ccx-healtho-card-item">
                <i class="fas fa-phone-alt"></i> +91 9700730044
            </a>
            <a href="mailto:Sales@healtho.in" class="ccx-healtho-card-item">
                <i class="fas fa-envelope"></i> Sales@healtho.in
            </a>
            <a href="https://healtho.in" target="_blank" class="ccx-healtho-card-item">
                <i class="fas fa-globe"></i> healtho.in
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var brandingTrigger = document.getElementById('ccxBrandingTrigger');
        var healthoCard = document.getElementById('ccxHealthoCard');

        if (brandingTrigger && healthoCard) {
            brandingTrigger.addEventListener('click', function (e) {
                e.stopPropagation();
                if (healthoCard.style.display === 'none' || healthoCard.style.display === '') {
                    healthoCard.style.display = 'block';
                    // Simple fade in effect
                    healthoCard.style.opacity = 0;
                    var opacity = 0;
                    var intervalID = setInterval(function () {
                        if (opacity < 1) {
                            opacity = opacity + 0.1;
                            healthoCard.style.opacity = opacity;
                        } else {
                            clearInterval(intervalID);
                        }
                    }, 20);
                } else {
                    healthoCard.style.display = 'none';
                }
            });

            document.addEventListener('click', function () {
                healthoCard.style.display = 'none';
            });

            healthoCard.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    });
</script>