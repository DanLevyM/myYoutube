import { Module } from '@nestjs/common';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { MailerModule } from '@nestjs-modules/mailer';
import { PugAdapter } from '@nestjs-modules/mailer/dist/adapters/pug.adapter';
import { MailService } from './services/mail/mail.service';
import { ConfigModule } from '@nestjs/config';
import { ServeStaticModule } from '@nestjs/serve-static/dist/serve-static.module';
import { join } from 'path';

@Module({
  imports: [
    ServeStaticModule.forRoot({
      rootPath: join(__dirname, '..', 'public'),
    }),
    MailerModule.forRootAsync({
      useFactory: () => ({
        transport: 'smtps://myyoutubapietna@gmail.com:ETNA2021@smtp.gmail.com',
        defaults: {
          from: '"no reply" <noreply@myYoutube.com>',
          name: 'MyYoutube'
        },
        template: {
          dir: __dirname + '/templates',
          // adapter: new PugAdapter({
          //   inlineCssEnabled: true,
          //   inlineCssOptions: { url: ' ' },
          // }),
          adapter: new PugAdapter(),
          options: {
            strict: true,
          },
        },
      }),
    }),
    ConfigModule.forRoot({ isGlobal: true }),
  ],
  controllers: [AppController],
  providers: [AppService, MailService],
})
export class AppModule {}
