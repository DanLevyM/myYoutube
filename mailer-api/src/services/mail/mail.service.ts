import { Injectable, NotFoundException } from '@nestjs/common';
import { MailerService } from '@nestjs-modules/mailer';

@Injectable()
export class MailService {
  constructor(private readonly mailerService: MailerService) { }

  async send(username: string, userEmail: string, task: any): Promise<any> {
    // console.log('Sender email from .env file : ', process.env.SENDER_MAIL);
    console.log('Email: ', userEmail, '\nName: ', username, '\nData:', task)
    try {
      return await this.mailerService.sendMail({
        from: process.env.SENDER_MAIL, // sender address
        to: userEmail, // list of receivers
        subject: 'Vidéo téléchargée ✔', // Subject line
        template: 'C:/wamp64/www/myYoutube/mailer-api/public/upload_success.pug',
        context: {
          // Data to be sent to template engine.👍🏿
          username: username,
          data: task,
        },
      });
    } catch (error) {
      return new NotFoundException('Error while trying to send mail !');
    }
  }
}
