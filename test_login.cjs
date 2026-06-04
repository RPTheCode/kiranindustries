const axios = require('axios');

async function testLogin() {
    try {
        // 1. Get the login page to get the XSRF-TOKEN cookie
        const res1 = await axios.get('http://127.0.0.1:8000/login');
        
        let cookies = res1.headers['set-cookie'] || [];
        let xsrfToken = '';
        let laravelSession = '';
        
        for (let cookie of cookies) {
            if (cookie.startsWith('XSRF-TOKEN=')) {
                xsrfToken = cookie.split(';')[0].split('=')[1];
            }
            if (cookie.startsWith('kiran_industries_session=')) {
                laravelSession = cookie.split(';')[0];
            }
        }
        
        console.log('XSRF-TOKEN:', decodeURIComponent(xsrfToken).substring(0, 20) + '...');
        
        // 2. Post to login
        const res2 = await axios.post('http://127.0.0.1:8000/login', {
            email: 'manager@gmail.com',
            password: 'password',
            remember: false
        }, {
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(xsrfToken),
                'Cookie': `XSRF-TOKEN=${xsrfToken}; ${laravelSession}`,
                'X-Inertia': 'true',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        console.log('Login Success! Status:', res2.status);
    } catch (error) {
        if (error.response) {
            console.log('Error Status:', error.response.status);
            console.log('Error Data:', error.response.data);
        } else {
            console.log('Error:', error.message);
        }
    }
}

testLogin();
