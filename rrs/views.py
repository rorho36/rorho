from django.shortcuts import render, redirect
from decimal import Decimal, InvalidOperation
from .models import DashboardProgress, Trade


def dashboard(request):
    obj, _ = DashboardProgress.objects.get_or_create(id=1)

    message = ''
    if request.method == 'POST':
        if 'update_current' in request.POST:
            amount = request.POST.get('current_amount')
            try:
                new_value = Decimal(amount)
                if new_value < 0:
                    message = 'Current amount must be non-negative.'
                else:
                    obj.current_amount = new_value
                    obj.save()
                    message = 'Current amount updated.'
                    return redirect('rrs:dashboard')
            except (InvalidOperation, TypeError):
                message = 'Please enter a valid number.'
        elif 'update_payouts' in request.POST:
            payout_amount = request.POST.get('overall_payouts')
            try:
                payout_value = Decimal(payout_amount)
                if payout_value < 0:
                    message = 'Overall payouts must be non-negative.'
                else:
                    obj.overall_payouts = payout_value
                    obj.save()
                    message = 'Overall payouts updated.'
                    return redirect('rrs:dashboard')
            except (InvalidOperation, TypeError):
                message = 'Please enter a valid number for overall payouts.'
        elif 'update_expenses' in request.POST:
            expense_amount = request.POST.get('overall_expenses')
            try:
                expense_value = Decimal(expense_amount)
                if expense_value < 0:
                    message = 'Overall expenses must be non-negative.'
                else:
                    obj.overall_expenses = expense_value
                    obj.save()
                    message = 'Overall expenses updated.'
                    return redirect('rrs:dashboard')
            except (InvalidOperation, TypeError):
                message = 'Please enter a valid number for overall expenses.'
        elif 'update_challenges' in request.POST:
            challenge_amount = request.POST.get('current_challenges')
            try:
                challenge_value = Decimal(challenge_amount)
                if challenge_value < 0:
                    message = 'Current challenges must be non-negative.'
                else:
                    obj.current_challenges = challenge_value
                    obj.save()
                    message = 'Current challenges updated.'
                    return redirect('rrs:dashboard')
            except (InvalidOperation, TypeError):
                message = 'Please enter a valid number for current challenges.'
        elif 'add_trade' in request.POST:
            date = request.POST.get('trade_date')
            amount = request.POST.get('trade_amount')
            firm = request.POST.get('trade_firm')
            action = request.POST.get('trade_action')
            existing = request.POST.get('trade_existing') == 'yes'
            
            if date or amount or firm or action:
                try:
                    trade_data = {'existing': existing}
                    if date:
                        trade_data['date'] = date
                    if amount:
                        trade_data['amount'] = Decimal(amount)
                    if firm:
                        trade_data['firm'] = firm
                    if action:
                        trade_data['action'] = action
                    
                    Trade.objects.create(**trade_data)
                    message = 'Trade added successfully.'
                    return redirect('rrs:dashboard')
                except (InvalidOperation, TypeError) as e:
                    message = f'Error adding trade: {str(e)}'

    trades = Trade.objects.all()[:10]  # Get last 10 trades

    context = {
        'goal_amount': obj.goal_amount,
        'current_amount': obj.current_amount,
        'overall_payouts': obj.overall_payouts,
        'overall_expenses': obj.overall_expenses,
        'current_challenges': obj.current_challenges,
        'fill_percent': obj.fill_percentage(),
        'message': message,
        'trades': trades,
    }
    return render(request, 'rrs/dashboard.html', context)

