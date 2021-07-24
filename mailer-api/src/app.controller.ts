import { MailService } from './services/mail/mail.service';
import { Body, Controller, Post } from '@nestjs/common';
import { AppService } from './app.service';

@Controller()
export class AppController {
  constructor(
    private readonly appService: AppService,
    private readonly mailService: MailService,
  ) {}

  @Post()
  async sendeEmail(@Body() data: any) {
    return (await this.mailService.send(data.username, data.email, data.task))
      .response;
  }
}
