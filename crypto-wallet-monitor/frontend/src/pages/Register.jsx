import { useState } from 'react';
import { Link } from 'react-router-dom';
import { UserPlus, MailCheck } from 'lucide-react';
import api from '../services/api';
import AuthLayout from '../components/AuthLayout';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { Alert, AlertDescription } from '../components/ui/alert';

function Register() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [registered, setRegistered] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    if (password !== passwordConfirmation) {
      setError('As senhas não coincidem.');
      return;
    }

    setSaving(true);

    try {
      await api.post('/register', {
        name,
        email,
        password,
      });

      setRegistered(true);
    } catch (err) {
      const validationErrors = err.response?.data?.errors;
      const firstError = validationErrors
        ? Object.values(validationErrors)[0]?.[0]
        : null;

      setError(firstError ?? 'Não foi possível criar a conta. Tente novamente.');
    } finally {
      setSaving(false);
    }
  }

  if (registered) {
    return (
      <AuthLayout title="Confirme seu email">
        <div className="flex flex-col items-start gap-3">
          <MailCheck className="size-6 text-primary" />
          <p className="text-sm text-muted-foreground">
            Cadastro realizado! Enviamos um link de confirmação para{' '}
            <span className="text-foreground">{email}</span>. Clique nele
            para poder entrar na sua conta.
          </p>
        </div>

        <p className="mt-4 text-sm text-muted-foreground">
          <Link to="/login" className="text-primary hover:underline">
            ← Voltar para o login
          </Link>
        </p>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout title="Criar conta">
      {error && (
        <Alert variant="destructive" className="mb-4">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <form onSubmit={handleSubmit} className="flex flex-col gap-3">
        <Input
          type="text"
          placeholder="Nome"
          value={name}
          onChange={e => setName(e.target.value)}
          disabled={saving}
          required
        />

        <Input
          type="email"
          placeholder="Email"
          value={email}
          onChange={e => setEmail(e.target.value)}
          disabled={saving}
          required
        />

        <Input
          type="password"
          placeholder="Senha"
          value={password}
          onChange={e => setPassword(e.target.value)}
          disabled={saving}
          required
        />

        <Input
          type="password"
          placeholder="Confirmar senha"
          value={passwordConfirmation}
          onChange={e => setPasswordConfirmation(e.target.value)}
          disabled={saving}
          required
        />

        <Button type="submit" disabled={saving} className="mt-2">
          <UserPlus className="size-4" />
          {saving ? 'Criando conta...' : 'Criar conta'}
        </Button>
      </form>

      <p className="mt-4 text-sm text-muted-foreground">
        Já tem conta?{' '}
        <Link to="/login" className="text-primary hover:underline">
          Entrar
        </Link>
      </p>
    </AuthLayout>
  );
}

export default Register;
