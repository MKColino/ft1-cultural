<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura Digital - Contrato <?php echo esc_html($contrato->numero_contrato); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .contract-number {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 0;
            min-height: 600px;
        }
        
        .contract-preview {
            padding: 30px;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            max-height: 70vh;
        }
        
        .contract-content {
            line-height: 1.6;
            color: #333;
        }
        
        .contract-content h2 {
            color: #2c3e50;
            margin: 20px 0 10px 0;
            font-size: 1.3rem;
        }
        
        .contract-content p {
            margin-bottom: 15px;
            text-align: justify;
        }
        
        .contract-info {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .signing-panel {
            padding: 30px;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .signing-section {
            margin-bottom: 30px;
        }
        
        .signing-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .signature-pad {
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: white;
            margin: 15px 0;
            position: relative;
            cursor: crosshair;
        }
        
        .signature-pad canvas {
            display: block;
            border-radius: 6px;
        }
        
        .signature-controls {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
            font-size: 16px;
            padding: 15px 30px;
        }
        
        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 20px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-top: 4px;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .legal-info {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 13px;
            color: #2c3e50;
        }
        
        .legal-info h4 {
            margin-bottom: 10px;
            color: #007bff;
        }
        
        .status-message {
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            font-weight: 500;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
            
            .contract-preview {
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
                max-height: 400px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
        
        .signature-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #999;
            font-size: 14px;
            pointer-events: none;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 500px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Assinatura Digital</h1>
            <div class="contract-number">Contrato Nº <?php echo esc_html($contrato->numero_contrato); ?></div>
        </div>
        
        <div class="content">
            <div class="contract-preview">
                <h2>Visualização do Contrato</h2>
                
                <div class="contract-info">
                    <strong>Informações do Contrato:</strong><br>
                    <strong>Número:</strong> <?php echo esc_html($contrato->numero_contrato); ?><br>
                    <strong>Projeto:</strong> <?php echo esc_html($contrato->projeto_titulo); ?><br>
                    <strong>Proponente:</strong> <?php echo esc_html($contrato->proponente_nome); ?><br>
                    <strong>Valor:</strong> R$ <?php echo number_format($contrato->valor, 2, ',', '.'); ?><br>
                    <strong>Vigência:</strong> <?php echo date('d/m/Y', strtotime($contrato->data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($contrato->data_fim)); ?>
                </div>
                
                <?php if ($contrato->pdf_path): ?>
                    <iframe src="<?php echo home_url("wp-admin/admin-ajax.php?action=ft1_get_contrato_pdf&id={$contract_id}&token={$token}"); ?>" 
                            class="pdf-viewer" 
                            frameborder="0">
                    </iframe>
                <?php else: ?>
                    <div class="contract-content">
                        <?php echo wp_kses_post($contrato->conteudo); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="signing-panel">
                <form id="signing-form">
                    <div class="signing-section">
                        <h3>Dados do Signatário</h3>
                        
                        <div class="form-group">
                            <label for="signer-name">Nome Completo:</label>
                            <input type="text" id="signer-name" class="form-control" 
                                   value="<?php echo esc_attr($contrato->proponente_nome); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="signer-email">Email:</label>
                            <input type="email" id="signer-email" class="form-control" 
                                   value="<?php echo esc_attr($contrato->proponente_email); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="signer-confirmation">Confirme seu nome para prosseguir:</label>
                            <input type="text" id="signer-confirmation" class="form-control" 
                                   placeholder="Digite seu nome completo" required>
                        </div>
                    </div>
                    
                    <div class="signing-section">
                        <h3>Assinatura Digital</h3>
                        <p>Desenhe sua assinatura no campo abaixo:</p>
                        
                        <div class="signature-pad" id="signature-pad">
                            <canvas width="340" height="150"></canvas>
                            <div class="signature-placeholder" id="signature-placeholder">
                                Clique e arraste para assinar
                            </div>
                        </div>
                        
                        <div class="signature-controls">
                            <button type="button" class="btn btn-secondary" id="clear-signature">
                                Limpar Assinatura
                            </button>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="accept-terms" required>
                        <label for="accept-terms">
                            Declaro que li e concordo com todos os termos do contrato acima. 
                            Confirmo que minha assinatura digital tem validade jurídica equivalente 
                            à assinatura manuscrita, conforme Lei 14.063/2020.
                        </label>
                    </div>
                    
                    <div class="legal-info">
                        <h4>Informações Legais</h4>
                        <p>Sua assinatura digital será registrada com as seguintes informações para garantir validade jurídica:</p>
                        <ul>
                            <li>Data e hora da assinatura</li>
                            <li>Endereço IP do dispositivo</li>
                            <li>Informações do navegador</li>
                            <li>Hash criptográfico do documento</li>
                        </ul>
                        <p><strong>Esta assinatura tem validade jurídica conforme a legislação brasileira.</strong></p>
                    </div>
                    
                    <div id="status-message"></div>
                    
                    <div class="loading" id="loading">
                        <div class="spinner"></div>
                        <p>Processando assinatura...</p>
                    </div>
                    
                    <button type="submit" class="btn btn-success" id="sign-button" disabled>
                        ✓ Assinar Contrato Digitalmente
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Signature pad functionality
        class SignaturePad {
            constructor(canvas) {
                this.canvas = canvas;
                this.ctx = canvas.getContext('2d');
                this.isDrawing = false;
                this.isEmpty = true;
                
                this.setupCanvas();
                this.bindEvents();
            }
            
            setupCanvas() {
                const rect = this.canvas.getBoundingClientRect();
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                
                this.canvas.width = rect.width * ratio;
                this.canvas.height = rect.height * ratio;
                
                this.ctx.scale(ratio, ratio);
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#000';
                this.ctx.lineWidth = 2;
            }
            
            bindEvents() {
                // Mouse events
                this.canvas.addEventListener('mousedown', (e) => this.startDrawing(e));
                this.canvas.addEventListener('mousemove', (e) => this.draw(e));
                this.canvas.addEventListener('mouseup', () => this.stopDrawing());
                this.canvas.addEventListener('mouseout', () => this.stopDrawing());
                
                // Touch events
                this.canvas.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const mouseEvent = new MouseEvent('mousedown', {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    this.canvas.dispatchEvent(mouseEvent);
                });
                
                this.canvas.addEventListener('touchmove', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const mouseEvent = new MouseEvent('mousemove', {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    this.canvas.dispatchEvent(mouseEvent);
                });
                
                this.canvas.addEventListener('touchend', (e) => {
                    e.preventDefault();
                    const mouseEvent = new MouseEvent('mouseup', {});
                    this.canvas.dispatchEvent(mouseEvent);
                });
            }
            
            startDrawing(e) {
                this.isDrawing = true;
                const rect = this.canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                this.ctx.beginPath();
                this.ctx.moveTo(x, y);
                
                if (this.isEmpty) {
                    this.isEmpty = false;
                    document.getElementById('signature-placeholder').style.display = 'none';
                    this.validateForm();
                }
            }
            
            draw(e) {
                if (!this.isDrawing) return;
                
                const rect = this.canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                this.ctx.lineTo(x, y);
                this.ctx.stroke();
            }
            
            stopDrawing() {
                this.isDrawing = false;
                this.ctx.beginPath();
            }
            
            clear() {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.isEmpty = true;
                document.getElementById('signature-placeholder').style.display = 'block';
                this.validateForm();
            }
            
            getDataURL() {
                return this.canvas.toDataURL();
            }
            
            validateForm() {
                const nameConfirmation = document.getElementById('signer-confirmation').value.trim();
                const originalName = document.getElementById('signer-name').value.trim();
                const termsAccepted = document.getElementById('accept-terms').checked;
                
                const isValid = !this.isEmpty && 
                               nameConfirmation.toLowerCase() === originalName.toLowerCase() && 
                               termsAccepted;
                
                document.getElementById('sign-button').disabled = !isValid;
            }
        }
        
        // Initialize signature pad
        const canvas = document.querySelector('#signature-pad canvas');
        const signaturePad = new SignaturePad(canvas);
        
        // Clear signature button
        document.getElementById('clear-signature').addEventListener('click', () => {
            signaturePad.clear();
        });
        
        // Form validation
        document.getElementById('signer-confirmation').addEventListener('input', () => {
            signaturePad.validateForm();
        });
        
        document.getElementById('accept-terms').addEventListener('change', () => {
            signaturePad.validateForm();
        });
        
        // Form submission
        document.getElementById('signing-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const signButton = document.getElementById('sign-button');
            const loading = document.getElementById('loading');
            const statusMessage = document.getElementById('status-message');
            
            // Show loading
            signButton.style.display = 'none';
            loading.style.display = 'block';
            statusMessage.innerHTML = '';
            
            try {
                const signatureData = {
                    signature: signaturePad.getDataURL(),
                    signer_name: document.getElementById('signer-name').value,
                    signer_email: document.getElementById('signer-email').value,
                    confirmation_name: document.getElementById('signer-confirmation').value,
                    timestamp: new Date().toISOString(),
                    user_agent: navigator.userAgent
                };
                
                const formData = new FormData();
                formData.append('action', 'ft1_sign_contrato');
                formData.append('nonce', '<?php echo wp_create_nonce("ft1_cultural_sign_nonce"); ?>');
                formData.append('id', '<?php echo $contract_id; ?>');
                formData.append('signature_data', JSON.stringify(signatureData));
                
                const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    statusMessage.innerHTML = `
                        <div class="status-success">
                            ✓ Contrato assinado com sucesso! 
                            Você receberá uma cópia por email em instantes.
                        </div>
                    `;
                    
                    // Disable form
                    document.getElementById('signing-form').style.opacity = '0.6';
                    document.getElementById('signing-form').style.pointerEvents = 'none';
                    
                } else {
                    throw new Error(result.data || 'Erro ao processar assinatura');
                }
                
            } catch (error) {
                statusMessage.innerHTML = `
                    <div class="status-error">
                        ✗ Erro: ${error.message}
                    </div>
                `;
                
                signButton.style.display = 'block';
            } finally {
                loading.style.display = 'none';
            }
        });
        
        // Initial form validation
        signaturePad.validateForm();
    </script>
</body>
</html>

