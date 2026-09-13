const Passkey = {
    bufferToBase64(buffer) {
        let binary = '';
        let bytes = new Uint8Array(buffer);
        let len = bytes.byteLength;
        for (let i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    },

    base64ToBuffer(base64) {
        let binary_string = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
        let len = binary_string.length;
        let bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binary_string.charCodeAt(i);
        }
        return bytes.buffer;
    },

    async register() {
        try {
            if (!window.PublicKeyCredential) {
                alert('Trình duyệt của bạn không hỗ trợ sinh trắc học / FaceID.');
                return false;
            }

            const res = await API.post('api/passkey.php?action=getRegisterArgs');
            if (!res.ok) {
                alert(res.error || 'Lỗi server');
                return false;
            }

            let args = res.args;
            args.challenge = this.base64ToBuffer(args.challenge);
            args.user.id = this.base64ToBuffer(args.user.id);
            if (args.excludeCredentials) {
                for (let cred of args.excludeCredentials) {
                    cred.id = this.base64ToBuffer(cred.id);
                }
            }

            const cred = await navigator.credentials.create({ publicKey: args });

            const data = {
                id: cred.id,
                clientDataJSON: this.bufferToBase64(cred.response.clientDataJSON),
                attestationObject: this.bufferToBase64(cred.response.attestationObject)
            };

            const verifyRes = await API.post('api/passkey.php?action=processRegister', data);
            if (verifyRes.ok) {
                alert('Đăng ký Vân tay / FaceID thành công!');
                return true;
            } else {
                alert(verifyRes.error || 'Xác thực thất bại.');
                return false;
            }

        } catch (e) {
            console.error(e);
            alert('Quá trình đăng ký bị hủy hoặc lỗi: ' + e.message);
            return false;
        }
    },

    async login() {
        try {
            if (!window.PublicKeyCredential) {
                alert('Trình duyệt của bạn không hỗ trợ sinh trắc học / FaceID.');
                return null;
            }

            const res = await API.post('api/passkey.php?action=getLoginArgs');
            if (!res.ok) {
                alert(res.error || 'Lỗi server');
                return null;
            }

            let args = res.args;
            args.challenge = this.base64ToBuffer(args.challenge);
            if (args.allowCredentials) {
                for (let cred of args.allowCredentials) {
                    cred.id = this.base64ToBuffer(cred.id);
                }
            }

            const cred = await navigator.credentials.get({ publicKey: args });

            const data = {
                id: cred.id,
                clientDataJSON: this.bufferToBase64(cred.response.clientDataJSON),
                authenticatorData: this.bufferToBase64(cred.response.authenticatorData),
                signature: this.bufferToBase64(cred.response.signature),
                userHandle: cred.response.userHandle ? this.bufferToBase64(cred.response.userHandle) : null
            };

            const verifyRes = await API.post('api/passkey.php?action=processLogin', data);
            if (verifyRes.ok) {
                return verifyRes.user;
            } else {
                alert(verifyRes.error || 'Đăng nhập sinh trắc học thất bại.');
                return null;
            }
        } catch (e) {
            console.error(e);
            if (e.name !== 'NotAllowedError') {
                alert('Lỗi đăng nhập sinh trắc học: ' + e.message);
            }
            return null;
        }
    }
};
window.Passkey = Passkey;
