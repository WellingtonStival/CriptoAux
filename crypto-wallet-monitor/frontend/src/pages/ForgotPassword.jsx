import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Send } from 'lucide-react';
import { forgotPassword } from '../services/api';
import AuthLayout from '../components/AuthLayout';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { Alert, AlertDescription } from '../components/ui/alert';

function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSaving(true);

    try {
      await forgotPassword(email);
      setSent(true);
    } catch {
      setError('Não foi possível enviar o link. Tente novamente.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <AuthLayout title="Esqueci minha senha">
      {sent ? (
        <p className="text-sm text-muted-foreground">
          Se esse email estiver cadastrado, enviamos um link de
          redefinição de senha para ele. Confira sua caixa de entrada.
        </p>
      ) : (
        <>
          <p className="mb-4 text-sm text-muted-foreground">
            Informe o email da sua conta para receber um link de
            redefinição de senha.
          </p>

          {error && (
            <Alert variant="destructive" className="mb-4">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          <form onSubmit={handleSubmit} className="flex flex-col gap-3">
            <Input
              type="email"
              placeholder="Email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              required
              disabled={saving}
            />

            <Button type="submit" disabled={saving} className="mt-2">
              <Send className="size-4" />
              {saving ? 'Enviando...' : 'Enviar link'}
            </Button>
          </form>
        </>
      )}

      <p className="mt-4 text-sm text-muted-foreground">
        <Link to="/login" className="text-primary hover:underline">
          ← Voltar para o login
        </Link>
      </p>
    </AuthLayout>
  );
}

export default ForgotPassword;
