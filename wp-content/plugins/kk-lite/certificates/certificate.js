// Certyfikat - obsługa danych i generowanie QR
(function() {
    'use strict';

    let certData = null;

    // Słuchaj wiadomości z rodzica
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'CERT_DATA') {
            certData = event.data.payload;
            populateCertificate(certData);
        }
    });

    function populateCertificate(data) {
        // Ustaw numer certyfikatu
        document.getElementById('certNo').textContent = data.certNo || '-';

        // Ustaw rolę i tytuł
        const role = data.role || 'KR';
        const roleLabels = {
            'KR': 'KOORDYNATOR RELACJI',
            'MR': 'MANAGER RELACJI',
            'RT': 'RECRUITMENT TEAM'
        };
        const roleDescs = {
            'KR': 'Koordynatora Relacji w systemie Werbekoordinator.pl',
            'MR': 'Managera Relacji w systemie Werbekoordinator.pl',
            'RT': 'Członka Zespołu Rekrutacyjnego w systemie Werbekoordinator.pl'
        };

        document.getElementById('roleTitle').textContent = data.roleLabel || roleLabels[role];
        document.getElementById('roleDesc').textContent = roleDescs[role];

        // Ustaw klasę dla kolorystyki
        const container = document.getElementById('certificate');
        container.className = 'certificate-container role-' + role.toLowerCase();

        // Ustaw daty
        document.getElementById('issueDate').textContent = data.issueDate || '-';
        document.getElementById('validUntil').textContent = data.validUntil || '-';

        // Ustaw dane osoby (opcjonalne)
        document.getElementById('personName').textContent = data.personName || '-';
        document.getElementById('userId').textContent = data.userId || '-';

        // Ustaw URL weryfikacji
        const verifyUrl = data.verifyUrl || ('https://werbekoordinator.pl/kk/weryfikacja/?cert_no=' + (data.certNo || ''));
        document.getElementById('verifyUrl').textContent = 'Weryfikuj: ' + verifyUrl;

        // Generuj QR code
        generateQRCode(verifyUrl);
    }

    function generateQRCode(url) {
        const qrContainer = document.getElementById('qrcode');
        
        // Wyczyść poprzedni QR
        qrContainer.innerHTML = '';

        // Generuj nowy QR code
        if (typeof QRCode !== 'undefined') {
            new QRCode(qrContainer, {
                text: url,
                width: 128,
                height: 128,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
        } else {
            qrContainer.innerHTML = '<div style="width:128px;height:128px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#999;">QR Code</div>';
        }
    }

    // Funkcja pobierania jako PNG
    window.downloadPNG = function() {
        // Użyj html2canvas jeśli dostępne, lub proste rozwiązanie
        if (typeof html2canvas !== 'undefined') {
            html2canvas(document.getElementById('certificate')).then(function(canvas) {
                const link = document.createElement('a');
                link.download = 'certyfikat-' + (certData ? certData.certNo : 'KK') + '.png';
                link.href = canvas.toDataURL();
                link.click();
            });
        } else {
            // Fallback - użyj SVG do canvas trick
            const cert = document.getElementById('certificate');
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // A4 w pikselach przy 96 DPI
            canvas.width = 794;
            canvas.height = 1123;
            
            // Biały background
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Tekst informacyjny (prosty fallback)
            ctx.fillStyle = 'black';
            ctx.font = '20px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Certyfikat KK', canvas.width/2, 100);
            
            if (certData) {
                ctx.fillText(certData.certNo, canvas.width/2, 150);
                ctx.fillText(certData.roleLabel, canvas.width/2, 200);
            }

            // Pobierz
            const link = document.createElement('a');
            link.download = 'certyfikat-' + (certData ? certData.certNo : 'KK') + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }
    };

    // Jeśli certyfikat jest otwierany bezpośrednio, załaduj przykładowe dane
    window.addEventListener('DOMContentLoaded', function() {
        // Poczekaj chwilę na wiadomość z rodzica
        setTimeout(function() {
            if (!certData) {
                // Brak danych - załaduj placeholder
                populateCertificate({
                    certNo: 'KR-20240101-0001',
                    role: 'KR',
                    roleLabel: 'KOORDYNATOR RELACJI',
                    issueDate: '1 stycznia 2024',
                    validUntil: '1 stycznia 2026',
                    verifyUrl: 'https://werbekoordinator.pl/kk/weryfikacja/?cert_no=KR-20240101-0001',
                    personName: '',
                    userId: ''
                });
            }
        }, 500);
    });
})();
