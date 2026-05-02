const nodemailer = require('nodemailer');

async function testEmail() {
  try {
    const transporter = nodemailer.createTransport({
      service: 'gmail',
      auth: {
        user: 'michaelangelojayuma1@gmail.com',
        pass: 'jfep pbjh byhn pqae'
      }
    });

    const info = await transporter.sendMail({
      from: 'michaelangelojayuma1@gmail.com',
      to: 'michaeljayuma7@gmail.com',
      subject: 'Test Email',
      text: 'This is a test email'
    });

    console.log('Success:', info.messageId);
  } catch (error) {
    console.error('Error:', error.message);
  }
}

testEmail();
