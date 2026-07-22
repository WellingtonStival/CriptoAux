import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { KeyRound } from 'lucide-react';
import { resetPassword } from '../services/api';
import AuthLayout from '../components/AuthLayout';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { Alert, AlertDescription } from '../components/ui/alert';

function ResetPassword() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    if (password !== passwordConfirmation) {
      setError('As senhas não coincidem.');
      return;
    }

    setSaving(true);

    try {
      await resetPassword({ token, email, password });
      navigate('/login');
    } catch (err) {
      const validationErrors = err.response?.data?.errors;
      const firstError = validationErrors
        ? Object.values(validationErrors)[0]?.[0]
        : err.response?.data?.message;

      setError(firstError ?? 'Não foi possível redefinir a senha. Tente novamente.');
    } finally {
      setSaving(false);
    }
  }

  if (!token || !email) {
    return (
      <AuthLayout title="Link inválido">
        <p className="text-sm text-muted-foreground">
          Este link de redefinição de senha está incompleto. Solicite um
          novo link.
        </p>
        <p className="mt-4 text-sm text-muted-foreground">
          <Link to="/esqueci-senha" className="text-primary hover:underline">
            Solicitar novo link
          </Link>
        </p>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout title="Redefinir senha">
      {error && (
        <Alert variant="destructive" className="mb-4">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <form onSubmit={handleSubmit} className="flex flex-col gap-3">
        <Input
          type="password"
          placeholder="Nova senha"
          value={password}
          onChange={e => setPassword(e.target.value)}
          required
          disabled={saving}
        />

        <Input
          type="password"
          placeholder="Confirmar nova senha"
          value={passwordConfirmation}
          onChange={e => setPasswordConfirmation(e.target.value)}
          required
          disabled={saving}
        />

        <Button type="submit" disabled={saving} className="mt-2">
          <KeyRound className="size-4" />
          {saving ? 'Salvando...' : 'Redefinir senha'}
        </Button>
      </form>
    </AuthLayout>
  );
}

export default ResetPassword;
