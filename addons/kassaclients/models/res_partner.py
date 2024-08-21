from odoo import models, fields, api
import pika
import json
import os
import multiprocessing

consumer_thread = None

class ResPartner(models.Model):
    _inherit = 'res.partner'

    firstname = fields.Char(string='First Name')
    wordpress_id = fields.Char('WordPress ID')

    def start(self):
        print("Starting consumer thread...")
        global consumer_thread
        consumer_thread = multiprocessing.Process(target=self.run)
        consumer_thread.start()

    def run(self):
        print("Syncing clients from RabbitMQ...")
        host = os.getenv("RABBITMQ_HOST", "rabbitmq")
        port = int(os.getenv("RABBITMQ_PORT", 5672))
        user = os.getenv("RABBITMQ_USER", "guest")
        password = os.getenv("RABBITMQ_PASSWORD", "guest")
        vhost = os.getenv("RABBITMQ_VHOST", "/")
        credentials = pika.PlainCredentials(user, password)
        params = pika.ConnectionParameters(
            host=host,
            port=port,
            credentials=credentials,
            virtual_host=vhost
        )
        print("Establishing connection to RabbitMQ...")
        connection = pika.BlockingConnection(params)
        channel = connection.channel()
        channel.queue_declare(queue='wordpress_updates', durable=True)

        def callback(ch, method, properties, body):
            try:
                data = json.loads(body)
                action = data.get('action')
                email = data.get('email')
                values = data.get('values', {})

                print(f"Received action: {action}, Email: {email}, Values: {values}")

                if action == 'create':
                    self.create(values)
                elif action == 'update':
                    records = self.search([('email', '=', email)])
                    if records:
                        records.write(values)
                    else:
                        print(f"Partner with email {email} not found for update.")
                elif action == 'delete':
                    if email:
                        records = self.search([('email', '=', email)])
                        if records:
                            records.unlink()
                            print(f"Deleted partner with email {email}")
                        else:
                            print(f"Partner with email {email} not found for deletion.")
                    else:
                        print(f"Email not provided for deletion.")

                ch.basic_ack(delivery_tag=method.delivery_tag)
            except Exception as e:
                print(f"Error processing message: {e}")
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=True)

        channel.basic_consume(queue='wordpress_updates', on_message_callback=callback, auto_ack=False)
        print(' [*] Waiting for messages. To exit press CTRL+C')
        channel.start_consuming()

    @api.model
    def send_update_to_rabbitmq(self, action, partner_id, values):
        print("Sending update to RabbitMQ...")

        try:
            host = os.getenv("RABBITMQ_HOST", "rabbitmq")
            port = int(os.getenv("RABBITMQ_PORT", 5672))
            user = os.getenv("RABBITMQ_USER", "guest")
            password = os.getenv("RABBITMQ_PASSWORD", "guest")
            vhost = os.getenv("RABBITMQ_VHOST", "/")
            credentials = pika.PlainCredentials(user, password)
            params = pika.ConnectionParameters(
                host=host,
                port=port,
                credentials=credentials,
                virtual_host=vhost
            )

            connection = pika.BlockingConnection(params)
            channel = connection.channel()
            channel.queue_declare(queue='odoo_updates', durable=True)

            message = {
                'action': action,
                'id': partner_id,
                'values': values
            }

            channel.basic_publish(
                exchange='',
                routing_key='odoo_updates',
                body=json.dumps(message),
                properties=pika.BasicProperties(delivery_mode=2)  # Persistent message
            )

            connection.close()
        except Exception as e:
            print(f"Error sending update to RabbitMQ: {e}")


    @api.model
    def create(self, vals):
        if 'name' not in vals or not vals['name']:
            vals['name'] = vals.get('firstname', 'Unnamed Partner')
        record = super(ResPartner, self).create(vals)
        self.send_update_to_rabbitmq('create', record.id, vals)
        return record

    def write(self, vals):
        result = super(ResPartner, self).write(vals)
        for record in self:
            self.send_update_to_rabbitmq('update', record.id, vals)
        return result
      


    def unlink(self):
        records = self.browse(self.ids)
        email_values = [{'email': record.email} for record in records]
        result = super(ResPartner, self).unlink()
        for email_value in email_values:
            self.send_update_to_rabbitmq('delete', None, email_value)  # ID is not needed for delete
        return result


